<?php

namespace GlpiPlugin\Ticketmigration\Plan;

final readonly class MigrationPlan
{
    public function __construct(
        public array $ticket,
        public array $actors = [],
        public array $followups = [],
        public array $tasks = [],
        public ?array $solution = null,
        public array $documents = [],
        public array $relationships = [],
        public array $externalReference = [],
        public array $information = [],
        public array $validations = [],
        public array $warnings = [],
        public array $errors = [],
    ) {}
    public function isExecutable(): bool { return $this->errors === []; }
}
