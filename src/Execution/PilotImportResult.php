<?php

namespace GlpiPlugin\Ticketmigration\Execution;

final readonly class PilotImportResult
{
    public function __construct(
        public int $ticketId,
        public int $runId,
        public bool $alreadyImported = false,
        public bool $inProgress = false,
    ) {}
}
