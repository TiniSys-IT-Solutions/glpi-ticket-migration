<?php

namespace GlpiPlugin\Ticketmigration\Source;

final readonly class SourceRow
{
    /** @param list<string|null> $values */
    public function __construct(public int $number, public array $values) {}
    public function value(int $columnIndex): ?string { return $this->values[$columnIndex] ?? null; }
}
