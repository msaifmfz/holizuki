<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Actions;

use App\Domain\Identity\Models\User;
use App\Domain\Publishing\Enums\PostSourceKind;
use App\Domain\Publishing\Models\Post;
use App\Domain\Publishing\Models\PostSource;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class StorePostSourceDocuments
{
    /**
     * @param  list<UploadedFile>  $documents
     * @return Collection<int, PostSource>
     */
    public function handle(Post $post, User $creator, array $documents): Collection
    {
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($post, $creator, $documents, &$storedPaths): Collection {
                $sources = new Collection;

                foreach ($documents as $document) {
                    $path = $document->store('posts/'.$post->id.'/sources', 'local');

                    if ($path === false) {
                        throw new RuntimeException('The source document could not be stored.');
                    }

                    $storedPaths[] = $path;
                    $originalName = $this->safeOriginalName($document);
                    $sources->push(PostSource::create([
                        'post_id' => $post->id,
                        'created_by_id' => $creator->id,
                        'kind' => PostSourceKind::Document,
                        'title' => $this->displayName($originalName),
                        'path' => $path,
                        'original_name' => $originalName,
                        'mime_type' => $document->getMimeType(),
                        'size_bytes' => $document->getSize(),
                    ]));
                }

                return $sources;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);

            throw $exception;
        }
    }

    private function safeOriginalName(UploadedFile $document): string
    {
        return Str::of($document->getClientOriginalName())
            ->replace('\\', '/')
            ->afterLast('/')
            ->limit(255, '')
            ->toString();
    }

    private function displayName(string $originalName): string
    {
        $displayName = Str::of($originalName)->beforeLast('.')->trim()->limit(255, '')->toString();

        return $displayName === '' ? __('Source document') : $displayName;
    }
}
