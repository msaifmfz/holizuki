<?php

declare(strict_types=1);

namespace App\Domain\Publishing\Actions;

use App\Domain\Publishing\Models\PostSource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

final class DeletePostSource
{
    public function handle(PostSource $source): void
    {
        $path = $source->path;

        DB::transaction(function () use ($source, $path): void {
            $source->delete();

            if ($path !== null) {
                DB::afterCommit(static fn (): bool => Storage::disk('local')->delete($path));
            }
        });
    }
}
