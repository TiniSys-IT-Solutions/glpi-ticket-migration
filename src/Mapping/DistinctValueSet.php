<?php

namespace GlpiPlugin\Ticketmigration\Mapping;

final readonly class DistinctValueSet
{
    public function __construct(public array $values, public bool $truncated) {}
}
