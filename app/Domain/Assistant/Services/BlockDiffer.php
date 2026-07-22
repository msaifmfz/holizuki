<?php

declare(strict_types=1);

namespace App\Domain\Assistant\Services;

/**
 * Block-level diff between two markdown renditions of a draft. Blocks are
 * separated by blank lines outside code fences; an LCS pass groups the
 * differences into contiguous hunks with the nearest unchanged blocks as
 * anchors, so a hunk can later be located inside a body that has drifted.
 */
class BlockDiffer
{
    /** @return list<array{old_blocks: string, new_blocks: string, anchor_before: string|null, anchor_after: string|null}> */
    public function diff(string $old, string $new): array
    {
        $oldBlocks = $this->splitBlocks($old);
        $newBlocks = $this->splitBlocks($new);

        if ($oldBlocks === $newBlocks) {
            return [];
        }

        $hunks = [];
        $pendingOld = [];
        $pendingNew = [];
        $anchorBefore = null;

        foreach ($this->operations($oldBlocks, $newBlocks) as [$operation, $block]) {
            if ($operation === 'keep') {
                if ($pendingOld !== [] || $pendingNew !== []) {
                    $hunks[] = [
                        'old_blocks' => implode("\n\n", $pendingOld),
                        'new_blocks' => implode("\n\n", $pendingNew),
                        'anchor_before' => $anchorBefore,
                        'anchor_after' => $block,
                    ];
                    $pendingOld = [];
                    $pendingNew = [];
                }

                $anchorBefore = $block;

                continue;
            }

            if ($operation === 'delete') {
                $pendingOld[] = $block;
            } else {
                $pendingNew[] = $block;
            }
        }

        if ($pendingOld !== [] || $pendingNew !== []) {
            $hunks[] = [
                'old_blocks' => implode("\n\n", $pendingOld),
                'new_blocks' => implode("\n\n", $pendingNew),
                'anchor_before' => $anchorBefore,
                'anchor_after' => null,
            ];
        }

        return $hunks;
    }

    /**
     * @return list<string>
     */
    public function splitBlocks(string $markdown): array
    {
        $blocks = [];
        $current = [];
        $fence = null;

        foreach (explode("\n", $markdown) as $line) {
            if ($fence === null && preg_match('/^\s*(`{3,})/', $line, $matches) === 1) {
                $fence = strlen($matches[1]);
                $current[] = $line;

                continue;
            }

            if ($fence !== null) {
                $current[] = $line;

                if (preg_match('/^\s*(`{3,})\s*$/', $line, $matches) === 1 && strlen($matches[1]) >= $fence) {
                    $fence = null;
                }

                continue;
            }

            if (trim($line) === '') {
                if ($current !== []) {
                    $blocks[] = implode("\n", $current);
                    $current = [];
                }

                continue;
            }

            $current[] = $line;
        }

        if ($current !== []) {
            $blocks[] = implode("\n", $current);
        }

        return $blocks;
    }

    /**
     * Longest-common-subsequence walk emitting keep/delete/insert operations.
     *
     * @param  list<string>  $old
     * @param  list<string>  $new
     * @return list<array{0: 'keep'|'delete'|'insert', 1: string}>
     */
    private function operations(array $old, array $new): array
    {
        $oldCount = count($old);
        $newCount = count($new);
        $lengths = array_fill(0, $oldCount + 1, array_fill(0, $newCount + 1, 0));

        for ($i = $oldCount - 1; $i >= 0; $i--) {
            for ($j = $newCount - 1; $j >= 0; $j--) {
                $lengths[$i][$j] = $old[$i] === $new[$j]
                    ? $lengths[$i + 1][$j + 1] + 1
                    : max($lengths[$i + 1][$j], $lengths[$i][$j + 1]);
            }
        }

        $operations = [];
        $i = 0;
        $j = 0;

        while ($i < $oldCount && $j < $newCount) {
            if ($old[$i] === $new[$j]) {
                $operations[] = ['keep', $old[$i]];
                $i++;
                $j++;
            } elseif ($lengths[$i + 1][$j] >= $lengths[$i][$j + 1]) {
                $operations[] = ['delete', $old[$i]];
                $i++;
            } else {
                $operations[] = ['insert', $new[$j]];
                $j++;
            }
        }

        for (; $i < $oldCount; $i++) {
            $operations[] = ['delete', $old[$i]];
        }

        for (; $j < $newCount; $j++) {
            $operations[] = ['insert', $new[$j]];
        }

        return $operations;
    }
}
