<?php

namespace GlpiPlugin\Ticketmigration\Mapping;

use GlpiPlugin\Ticketmigration\Source\SourceReaderInterface;

final class DistinctValueCollector
{
    public function collect(SourceReaderInterface $reader, array $columnIndexes, int $limitPerColumn = 200, array $multiDelimiters = []): array
    {
        $values = array_fill_keys($columnIndexes, []);
        $truncated = array_fill_keys($columnIndexes, false);
        foreach ($reader->rows() as $row) {
            foreach ($columnIndexes as $index) {
                foreach ($this->splitValue((string) $row->value((int) $index), $multiDelimiters[$index] ?? null) as $value) {
                    if ($value === '') {
                        continue;
                    }
                    $hash = hash('sha256', $value);
                    if (!isset($values[$index][$hash])) {
                        if (count($values[$index]) >= $limitPerColumn) {
                            $truncated[$index] = true;
                            continue;
                        }
                        $values[$index][$hash] = $value;
                    }
                }
            }
        }
        $result = [];
        foreach ($columnIndexes as $index) {
            natcasesort($values[$index]);
            $result[$index] = new DistinctValueSet(array_values($values[$index]), $truncated[$index]);
        }
        return $result;
    }

    public function splitValue(string $value, ?string $delimiter): array
    {
        $pattern = match ($delimiter) {
            'comma' => '/,/',
            'semicolon' => '/;/',
            'pipe' => '/\|/',
            'newline' => '/\R/u',
            'auto' => '/[;|]|\R/u',
            default => null,
        };
        $parts = $pattern === null ? [$value] : (preg_split($pattern, $value) ?: [$value]);
        return array_values(array_filter(array_map('trim', $parts), static fn (string $part): bool => $part !== ''));
    }
}
