<?php

namespace GlpiPlugin\Ticketmigration\Source;

final readonly class SourceColumn
{
    public function __construct(public int $index, public string $name) {}
    public function key(): string { return sprintf('%d:%s', $this->index, $this->name); }
}
