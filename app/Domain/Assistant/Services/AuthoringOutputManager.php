<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Services;

use App\Domain\Assistant\Models\AssistantTurn;
use App\Domain\Publishing\Models\Post;
use Illuminate\Filesystem\Filesystem;
use JsonException;
use UnexpectedValueException;

final readonly class AuthoringOutputManager
{
    public const string FILENAME = 'authoring-output.json';

    public function __construct(
        private Filesystem $files,
        private WorkspaceManager $workspace,
    ) {}

    public function prepare(Post $post, AssistantTurn $turn): void
    {
        $path = $this->path($post, $turn);
        $this->files->put($path, "{}\n");
        $this->files->chmod($path, 0600);
    }

    /** @return array<string, mixed> */
    public function read(Post $post, AssistantTurn $turn): array
    {
        $path = $this->path($post, $turn);

        if (is_link($path) || ! is_file($path)) {
            throw new UnexpectedValueException('The guided authoring output must be a regular file.');
        }

        $size = filesize($path);
        $limit = config()->integer('assistant.limits.authoring_output_bytes', 4 * 1024 * 1024);

        if (! is_int($size) || $size > $limit) {
            throw new UnexpectedValueException('The guided authoring output is too large.');
        }

        $contents = $this->files->get($path);

        try {
            $decoded = json_decode($contents, true, 128, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new UnexpectedValueException('The guided authoring output must be valid JSON.', $exception->getCode(), previous: $exception);
        }

        if (! is_array($decoded) || array_is_list($decoded)) {
            throw new UnexpectedValueException('The guided authoring output must be a JSON object.');
        }

        $output = [];

        foreach ($decoded as $key => $value) {
            if (! is_string($key)) {
                throw new UnexpectedValueException('The guided authoring output must use named fields.');
            }

            $output[$key] = $value;
        }

        return $output;
    }

    private function path(Post $post, AssistantTurn $turn): string
    {
        return $this->workspace->pathFor($post, $turn).'/'.self::FILENAME;
    }
}
