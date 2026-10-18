<?php

namespace App\Support;

/**
 * Safe CSV rows: text a person typed (a name, a description) that starts like a spreadsheet
 * formula is prefixed with an apostrophe so Excel or Sheets shows it instead of running it.
 */
class Csv
{
    /**
     * @param  iterable<mixed>  $row
     * @return list<mixed>
     */
    public static function row(iterable $row): array
    {
        $out = [];
        foreach ($row as $value) {
            $out[] = is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) && ! is_numeric($value) ? "'".$value : $value;
        }

        return $out;
    }
}
