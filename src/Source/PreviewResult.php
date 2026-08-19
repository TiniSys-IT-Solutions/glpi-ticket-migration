<?php

namespace GlpiPlugin\Ticketmigration\Source;

final readonly class PreviewResult
{
    /** @param list<SourceColumn> $columns @param list<SourceRow> $rows */
    public function __construct(
        public array $columns,
        public array $rows,
        public string $schemaFingerprint,
    ) {}
}
