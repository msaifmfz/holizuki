<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Services;

use Carbon\CarbonInterface;
use FilesystemIterator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use SplFileInfo;
use Throwable;

/**
 * Deletes Claude Code transcripts and session artifacts from the dedicated
 * assistant HOME without following links outside its Claude data directory.
 */
final readonly class ClaudeTranscriptPruner
{
    public function __construct(private Filesystem $files) {}

    /** @param list<string> $sessionIds */
    public function deleteSessions(array $sessionIds): int
    {
        $sessionIds = $this->validSessionIds($sessionIds);
        $deleted = 0;

        foreach ($this->artifacts($sessionIds) as $artifact) {
            if ($this->deleteArtifact($artifact)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /** @param list<string> $retainedSessionIds */
    public function deleteExpiredExcept(
        array $retainedSessionIds,
        CarbonInterface $cutoff,
    ): int {
        $retained = array_fill_keys($this->validSessionIds($retainedSessionIds), true);
        $deleted = 0;

        foreach ($this->artifacts() as $sessionId => $artifact) {
            if (isset($retained[$sessionId])) {
                continue;
            }

            if ($artifact['modified_at'] > $cutoff->getTimestamp()) {
                continue;
            }

            if ($this->deleteArtifact($artifact)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * @param  list<string>|null  $onlySessionIds
     * @return array<string, array{
     *     files: list<array{path: string, parent: string}>,
     *     directories: list<array{path: string, parent: string}>,
     *     modified_at: int
     * }>
     */
    private function artifacts(?array $onlySessionIds = null): array
    {
        if ($onlySessionIds === []) {
            return [];
        }

        $only = $onlySessionIds === null
            ? null
            : array_fill_keys($onlySessionIds, true);
        $artifacts = [];
        $projectsRoot = $this->claudeSubdirectory('projects');

        if ($projectsRoot !== null) {
            foreach ($this->directDirectories($projectsRoot) as $projectDirectory) {
                foreach ($this->directFiles($projectDirectory) as $transcript) {
                    if (pathinfo($transcript, PATHINFO_EXTENSION) !== 'jsonl') {
                        continue;
                    }

                    $sessionId = pathinfo($transcript, PATHINFO_FILENAME);

                    if (! $this->includesSession($sessionId, $only)) {
                        continue;
                    }

                    $this->addFile($artifacts, $sessionId, $transcript, $projectDirectory);
                }

                foreach ($this->directDirectories($projectDirectory) as $sessionDirectory) {
                    $sessionId = basename($sessionDirectory);

                    if (! $this->includesSession($sessionId, $only)) {
                        continue;
                    }

                    $this->addDirectory($artifacts, $sessionId, $sessionDirectory, $projectDirectory);
                }
            }
        }

        $fileHistoryRoot = $this->claudeSubdirectory('file-history');

        if ($fileHistoryRoot !== null) {
            foreach ($this->directDirectories($fileHistoryRoot) as $sessionDirectory) {
                $sessionId = basename($sessionDirectory);

                if (! $this->includesSession($sessionId, $only)) {
                    continue;
                }

                $this->addDirectory($artifacts, $sessionId, $sessionDirectory, $fileHistoryRoot);
            }
        }

        return $artifacts;
    }

    /**
     * @param  array<string, array{
     *     files: list<array{path: string, parent: string}>,
     *     directories: list<array{path: string, parent: string}>,
     *     modified_at: int
     * }>  $artifacts
     */
    private function addFile(
        array &$artifacts,
        string $sessionId,
        string $path,
        string $parent,
    ): void {
        $artifact = $artifacts[$sessionId] ?? [
            'files' => [],
            'directories' => [],
            'modified_at' => 0,
        ];
        $artifact['files'][] = ['path' => $path, 'parent' => $parent];
        $artifact['modified_at'] = max($artifact['modified_at'], $this->modifiedAt($path));
        $artifacts[$sessionId] = $artifact;
    }

    /**
     * @param  array<string, array{
     *     files: list<array{path: string, parent: string}>,
     *     directories: list<array{path: string, parent: string}>,
     *     modified_at: int
     * }>  $artifacts
     */
    private function addDirectory(
        array &$artifacts,
        string $sessionId,
        string $path,
        string $parent,
    ): void {
        $artifact = $artifacts[$sessionId] ?? [
            'files' => [],
            'directories' => [],
            'modified_at' => 0,
        ];
        $artifact['directories'][] = ['path' => $path, 'parent' => $parent];
        $artifact['modified_at'] = max($artifact['modified_at'], $this->modifiedAt($path));
        $artifacts[$sessionId] = $artifact;
    }

    /**
     * @param  array{
     *     files: list<array{path: string, parent: string}>,
     *     directories: list<array{path: string, parent: string}>,
     *     modified_at: int
     * }  $artifact
     */
    private function deleteArtifact(array $artifact): bool
    {
        $deleted = false;

        foreach ($artifact['files'] as $file) {
            if ($this->deleteDirectFile($file['path'], $file['parent'])) {
                $deleted = true;
            }
        }

        foreach ($artifact['directories'] as $directory) {
            if ($this->deleteDirectDirectory($directory['path'], $directory['parent'])) {
                $deleted = true;
            }
        }

        return $deleted;
    }

    /**
     * @param  array<string, true>|null  $only
     */
    private function includesSession(string $sessionId, ?array $only): bool
    {
        return Str::isUuid($sessionId)
            && ($only === null || isset($only[$sessionId]));
    }

    /**
     * @param  list<string>  $sessionIds
     * @return list<string>
     */
    private function validSessionIds(array $sessionIds): array
    {
        return array_values(array_unique(array_filter(
            $sessionIds,
            fn (string $sessionId): bool => Str::isUuid($sessionId),
        )));
    }

    private function claudeSubdirectory(string $name): ?string
    {
        $home = config()->string('assistant.home', storage_path('app/assistant/home'));

        if (is_link($home)) {
            return null;
        }

        $homeRoot = realpath($home);

        if ($homeRoot === false || ! $this->files->isDirectory($homeRoot)) {
            return null;
        }

        $claudeRoot = $this->directDirectory($homeRoot.'/.claude', $homeRoot);

        if ($claudeRoot === null) {
            return null;
        }

        return $this->directDirectory($claudeRoot.'/'.$name, $claudeRoot);
    }

    /** @return list<string> */
    private function directDirectories(string $parent): array
    {
        $directories = [];

        try {
            foreach (new FilesystemIterator($parent, FilesystemIterator::SKIP_DOTS) as $item) {
                if (! $item instanceof SplFileInfo) {
                    continue;
                }
                if ($item->isLink()) {
                    continue;
                }
                if (! $item->isDir()) {
                    continue;
                }
                $directory = $this->directDirectory($item->getPathname(), $parent);

                if ($directory !== null) {
                    $directories[] = $directory;
                }
            }
        } catch (Throwable) {
            return [];
        }

        sort($directories);

        return $directories;
    }

    /** @return list<string> */
    private function directFiles(string $parent): array
    {
        $files = [];

        try {
            foreach (new FilesystemIterator($parent, FilesystemIterator::SKIP_DOTS) as $item) {
                if (! $item instanceof SplFileInfo) {
                    continue;
                }
                if ($item->isLink()) {
                    continue;
                }
                if (! $item->isFile()) {
                    continue;
                }
                $file = $this->directFile($item->getPathname(), $parent);

                if ($file !== null) {
                    $files[] = $file;
                }
            }
        } catch (Throwable) {
            return [];
        }

        sort($files);

        return $files;
    }

    private function directDirectory(string $path, string $parent): ?string
    {
        if (is_link($path)) {
            return null;
        }

        $realPath = realpath($path);

        if (
            $realPath === false
            || ! $this->files->isDirectory($realPath)
            || dirname($realPath) !== $parent
        ) {
            return null;
        }

        return $realPath;
    }

    private function directFile(string $path, string $parent): ?string
    {
        if (is_link($path)) {
            return null;
        }

        $realPath = realpath($path);

        if (
            $realPath === false
            || ! is_file($realPath)
            || dirname($realPath) !== $parent
        ) {
            return null;
        }

        return $realPath;
    }

    private function deleteDirectDirectory(string $path, string $parent): bool
    {
        $directory = $this->directDirectory($path, $parent);

        if ($directory === null || ! $this->files->deleteDirectory($directory)) {
            return false;
        }

        return ! file_exists($directory) && ! is_link($directory);
    }

    private function deleteDirectFile(string $path, string $parent): bool
    {
        $file = $this->directFile($path, $parent);

        return $file !== null && $this->files->delete($file);
    }

    private function modifiedAt(string $path): int
    {
        try {
            if (is_link($path)) {
                return PHP_INT_MAX;
            }

            $modifiedAt = filemtime($path);

            if ($modifiedAt === false || ! is_dir($path)) {
                return $modifiedAt === false ? PHP_INT_MAX : $modifiedAt;
            }

            foreach (new FilesystemIterator($path, FilesystemIterator::SKIP_DOTS) as $item) {
                if (! $item instanceof SplFileInfo) {
                    continue;
                }
                if ($item->isLink()) {
                    continue;
                }
                $modifiedAt = max($modifiedAt, $this->modifiedAt($item->getPathname()));
            }
        } catch (Throwable) {
            return PHP_INT_MAX;
        }

        return $modifiedAt;
    }
}
