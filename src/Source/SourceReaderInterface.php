<?php

namespace GlpiPlugin\Ticketmigration\Source;

interface SourceReaderInterface
{
    /** @return iterable<SourceRow> */
    public function rows(): iterable;
    /** @return list<SourceColumn> */
    public function columns(): array;
    /** @return list<SourceRow> */
    public function preview(int $limit = 10): array;
}
