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
        if ($delimiter === 'auto') {
            $parts = preg_split('/[;|]|\R/u', $value) ?: [$value];
            $detected = [];
            foreach ($parts as $part) {
                $commaParts = array_map('trim', explode(',', $part));
                $containsEmail = count($commaParts) > 1
                    && array_filter($commaParts, static fn (string $candidate): bool => filter_var($candidate, FILTER_VALIDATE_EMAIL) !== false) !== [];
                array_push($detected, ...($containsEmail ? $commaParts : [trim($part)]));
            }
            return array_values(array_filter($detected, static fn (string $part): bool => $part !== ''));
        }
        $pattern = match ($delimiter) {
            'comma' => '/,/',
            'semicolon' => '/;/',
            'pipe' => '/\|/',
            'newline' => '/\R/u',
            default => null,
        };
        $parts = $pattern === null ? [$value] : (preg_split($pattern, $value) ?: [$value]);
        return array_values(array_filter(array_map('trim', $parts), static fn (string $part): bool => $part !== ''));
    }
}
