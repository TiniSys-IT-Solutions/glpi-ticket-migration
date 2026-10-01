<?php

namespace GlpiPlugin\Ticketmigration\Source;

final class SchemaFingerprint
{
    /** @param list<SourceColumn> $columns */
    public function compute(array $columns, CsvConfiguration $configuration): string
    {
        $schema = [
            'version' => 1,
            'csv' => [
                'delimiter' => $configuration->delimiter,
                'enclosure' => $configuration->enclosure,
                'escape' => $configuration->escape,
                'has_header' => $configuration->hasHeader,
                'encoding' => strtoupper($configuration->encoding),
            ],
            'columns' => array_map(
                static fn (SourceColumn $column): array => ['index' => $column->index, 'name' => $column->name],
                $columns,
            ),
        ];
        return hash('sha256', json_encode($schema, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
