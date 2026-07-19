<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * SQL fragments that differ per database driver. Columns must be trusted
 * identifiers, never user input.
 */
class DbExpressions
{
    /**
     * @param  literal-string  $column
     * @return literal-string
     */
    public static function yearOf(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%Y', $column)"
            : "extract(year from $column)";
    }

    /**
     * @param  literal-string  $column
     * @return literal-string
     */
    public static function monthOf(string $column): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "strftime('%m', $column)"
            : "extract(month from $column)";
    }
}
