<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Console;

use App\Domain\Publishing\Actions\RebuildPostMetadata;
use App\Domain\Publishing\Models\Post;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('posts:rebuild-metadata {--missing : Rebuild only posts without derived metadata}')]
#[Description('Rebuild post reading-time, word-count, and search metadata')]
class RebuildPostMetadataCommand extends Command
{
    public function handle(RebuildPostMetadata $rebuildPostMetadata): int
    {
        $query = Post::withTrashed();

        if ($this->option('missing')) {
            $query->where(function (Builder $query): void {
                $query
                    ->whereNull('reading_time_minutes')
                    ->orWhereNull('search_text')
                    ->orWhere('word_count', 0);
            });
        }

        $rebuilt = $rebuildPostMetadata->handleQuery($query);
        $this->components->info(__('Rebuilt metadata for :count posts.', ['count' => $rebuilt]));

        return self::SUCCESS;
    }
}
