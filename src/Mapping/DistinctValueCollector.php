<?php

namespace GlpiPlugin\Ticketmigration\Mapping;

use GlpiPlugin\Ticketmigration\Source\SourceReaderInterface;

final class DistinctValueCollector
{
    public function collect(SourceReaderInterface $reader, array $columnIndexes, int $limitPerColumn = 200): array
    {
        $values = array_fill_keys($columnIndexes, []);
        $truncated = array_fill_keys($columnIndexes, false);
        foreach ($reader->rows() as $row) {
            foreach ($columnIndexes as $index) {
                $value = trim((string) $row->value((int) $index));
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
        $result = [];
        foreach ($columnIndexes as $index) {
            natcasesort($values[$index]);
            $result[$index] = new DistinctValueSet(array_values($values[$index]), $truncated[$index]);
        }
        return $result;
    }
}
