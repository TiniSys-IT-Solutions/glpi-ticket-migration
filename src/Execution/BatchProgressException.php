<?php

namespace GlpiPlugin\Ticketmigration\Execution;

final class BatchProgressException extends \RuntimeException
{
    public function __construct(
        public readonly string $reason,
        public readonly int $offset,
        public readonly int $totalRows,
    ) {
        parent::__construct(sprintf('%s at offset %d of %d.', $reason, $offset, $totalRows));
    }
}
