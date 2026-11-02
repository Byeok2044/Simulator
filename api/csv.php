<?php
/** Strict CSV reader with bounded input, quoted newlines and escaped quotes. */
function parseCsvText(string $text, string $delimiter = ','): array {
    if (strlen($text) > 2 * 1024 * 1024) throw new InvalidArgumentException('Choose a CSV file no larger than 2 MB.');
    if (!in_array($delimiter, [',', ';', "\t"], true)) throw new InvalidArgumentException('Choose a valid CSV separator.');
    if (preg_match('//u', $text) !== 1 || str_contains($text, "\0")) throw new InvalidArgumentException('Save your CSV as UTF-8 text before importing.');
    $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
    $rows = []; $row = []; $field = ''; $quoted = false; $closed = false;
    $pushField = function () use (&$row, &$field, &$closed) {
        $row[] = $field; $field = ''; $closed = false;
        if (count($row) > 200) throw new InvalidArgumentException('Use at most 200 columns.');
    };
    $pushRow = function () use (&$rows, &$row, $pushField) {
        $pushField();
        if (array_filter($row, fn($value) => trim($value) !== '')) $rows[] = $row;
        $row = [];
        if (count($rows) > 10001) throw new InvalidArgumentException('Use at most 10,000 data records plus a header.');
    };
    for ($i = 0, $length = strlen($text); $i < $length; $i++) {
        $char = $text[$i];
        if ($quoted) {
            if ($char === '"') {
                if (($text[$i + 1] ?? '') === '"') { $field .= '"'; $i++; }
                else { $quoted = false; $closed = true; }
            } else $field .= $char;
        } elseif ($char === $delimiter) $pushField();
        elseif ($char === "\r" || $char === "\n") {
            if ($char === "\r" && ($text[$i + 1] ?? '') === "\n") $i++;
            $pushRow();
        } elseif ($char === '"' && $field === '' && !$closed) $quoted = true;
        elseif ($closed || $char === '"') throw new InvalidArgumentException('Invalid quoting near CSV record ' . (count($rows) + 1) . '.');
        else $field .= $char;
    }
    if ($quoted) throw new InvalidArgumentException('A quoted CSV field is missing its closing quote.');
    $pushRow();
    if (!$rows) throw new InvalidArgumentException('This file contains no data.');
    return $rows;
}

function validNumber($value): bool {
    return (is_string($value) || is_int($value) || is_float($value))
        && is_numeric($value) && is_finite((float)$value) && abs((float)$value) <= 1e100;
}

function csvPairs(array $rows, bool $header, int $xColumn, int $yColumn, bool $skip): array {
    $width = max(array_map('count', $rows ?: [[]]));
    if ($xColumn < 0 || $yColumn < 0 || $xColumn >= $width || $yColumn >= $width) throw new InvalidArgumentException('Choose valid CSV columns.');
    if ($xColumn === $yColumn) throw new InvalidArgumentException('Choose different columns for X and Y.');
    if (count($rows) - (int)$header > 10000) throw new InvalidArgumentException('Use at most 10,000 data records.');
    $pairs = []; $invalid = [];
    foreach (array_slice($rows, (int)$header) as $i => $row) {
        if (!validNumber($row[$xColumn] ?? null) || !validNumber($row[$yColumn] ?? null)) $invalid[] = $i + (int)$header + 1;
        else $pairs[] = [trim($row[$xColumn]), trim($row[$yColumn])];
    }
    if ($invalid && !$skip) throw new InvalidArgumentException('Missing, non-numeric or out-of-range values in CSV records: ' . implode(', ', array_slice($invalid, 0, 8)) . '. Fix them or enable skipping invalid records.');
    if (count($pairs) < 2) throw new InvalidArgumentException('At least 2 valid data points are required.');
    if (count($pairs) > 5000) throw new InvalidArgumentException('Use at most 5,000 valid data points.');
    return ['pairs' => $pairs, 'skipped' => count($invalid)];
}
