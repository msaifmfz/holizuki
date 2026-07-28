<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Services;

use App\Domain\Assistant\Models\AssistantTurn;
use App\Domain\Assistant\Models\AuthoringTask;
use App\Domain\Publishing\Enums\PostSourceKind;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostSource;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use UnexpectedValueException;

final readonly class AuthoringSourceWorkspace
{
    public function __construct(
        private Filesystem $files,
        private WorkspaceManager $workspace,
    ) {}

    public function materialize(Post $post, AssistantTurn $turn, AuthoringTask $task): void
    {
        $sourceIds = $task->context['source_ids'] ?? [];

        if (! is_array($sourceIds)) {
            $sourceIds = [];
        }

        $sources = PostSource::query()
            ->whereBelongsTo($post)
            ->whereNotNull('approved_at')
            ->whereIn('id', array_values(array_filter($sourceIds, is_int(...))))
            ->oldest('id')
            ->get();

        $path = $this->workspace->pathFor($post, $turn).'/sources';
        $this->files->ensureDirectoryExists($path);
        $this->files->chmod($path, 0700);
        $manifest = [];
        $totalBytes = 0;

        foreach ($sources as $source) {
            $file = $this->materializeSource($source, $path);

            if ($file !== null) {
                $totalBytes += (int) filesize($path.'/'.$file);

                if ($totalBytes > config()->integer('assistant.limits.source_bytes', 100 * 1024 * 1024)) {
                    throw new UnexpectedValueException('The approved source files exceed the assistant source limit.');
                }
            }

            $manifest[] = [
                'id' => $source->id,
                'kind' => $source->kind->value,
                'title' => $source->title,
                'url' => $source->url,
                'file' => $file === null ? null : 'sources/'.$file,
                'approved' => true,
            ];
        }

        $manifestPath = $path.'/manifest.json';
        $encoded = json_encode(
            ['sources' => $manifest],
            JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
        $this->files->put($manifestPath, $encoded."\n");
        $this->files->chmod($manifestPath, 0600);
    }

    private function materializeSource(PostSource $source, string $path): ?string
    {
        if ($source->kind === PostSourceKind::Note && $source->content !== null) {
            $filename = 'source-'.$source->id.'.txt';
            $this->putPrivate($path.'/'.$filename, $source->content);

            return $filename;
        }

        if ($source->kind !== PostSourceKind::Document || $source->path === null) {
            return null;
        }

        $sourcePath = $this->safeLocalPath($source->path);
        $extension = Str::lower(pathinfo($source->path, PATHINFO_EXTENSION));

        if (! in_array($extension, ['pdf', 'txt', 'md'], true)) {
            throw new UnexpectedValueException('An approved source has an unsupported file type.');
        }

        $filename = 'source-'.$source->id.'.'.$extension;

        if (! $this->files->copy($sourcePath, $path.'/'.$filename)) {
            throw new RuntimeException('An approved source could not be copied into the assistant workspace.');
        }

        $this->files->chmod($path.'/'.$filename, 0600);

        return $filename;
    }

    private function safeLocalPath(string $storagePath): string
    {
        $path = Storage::disk('local')->path($storagePath);
        $root = realpath(Storage::disk('local')->path(''));
        $realPath = realpath($path);

        if (
            $root === false
            || $realPath === false
            || is_link($path)
            || ! is_file($realPath)
            || ! str_starts_with($realPath, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)
        ) {
            throw new UnexpectedValueException('Approved source documents must be regular private files.');
        }

        return $realPath;
    }

    private function putPrivate(string $path, string $contents): void
    {
        $this->files->put($path, $contents);
        $this->files->chmod($path, 0600);
    }
}
