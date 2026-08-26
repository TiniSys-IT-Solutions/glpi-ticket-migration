<?php

namespace GlpiPlugin\Ticketmigration\Execution;

use Glpi\Error\ErrorHandler;
use GlpiPlugin\Ticketmigration\Idempotency\CanonicalRowHasher;
use GlpiPlugin\Ticketmigration\MigrationProfile;
use GlpiPlugin\Ticketmigration\Plan\MigrationPlan;
use GlpiPlugin\Ticketmigration\Plan\MigrationPlanBuilder;
use GlpiPlugin\Ticketmigration\Source\CsvReader;
use GlpiPlugin\Ticketmigration\Source\SourceRow;
use GlpiPlugin\Ticketmigration\SourceFile;

final class MassImportService
{
    public function process(int $runId, MigrationProfile $profile, SourceFile $source, CsvReader $reader, int $limit = 25): array
    {
        $repository = new RunRepository();
        $run = $repository->get($runId);
        if ($run === null || !in_array($run['status'], ['queued', 'running'], true)) { return $run ?? []; }
        if (empty($run['backup_confirmed_at']) || (int) ($run['backup_confirmed_by'] ?? 0) <= 0) {
            throw new \RuntimeException('The backup responsibility acknowledgement is missing.');
        }
        if (!hash_equals((string) $run['source_hash'], (string) $source->fields['sha256'])) {
            $repository->update($runId, ['status' => 'failed', 'finished_at' => $this->now()]);
            throw new \RuntimeException('The source snapshot no longer matches this run.');
        }
        if ($run['status'] === 'queued') {
            $repository->update($runId, ['status' => 'running', 'started_at' => $this->now()]);
        }
        $profileId = (int) $profile->getID();
        $snapshot = json_decode((string) ($run['configuration_snapshot'] ?? ''), true);
        if (!is_array($snapshot)) { throw new \RuntimeException('The immutable run configuration is unavailable.'); }
        $fieldMappings = (array) ($snapshot['field_mappings'] ?? []);
        $valueMappings = (array) ($snapshot['value_mappings'] ?? []);
        $options = (array) ($snapshot['options'] ?? []);
        $context = (array) ($snapshot['entity_context'] ?? []);
        $builder = new MigrationPlanBuilder();
        foreach ($reader->batch((int) $run['current_offset'], $limit) as $row) {
            $plan = $builder->build($row, $fieldMappings, $valueMappings, $reader->columns(),
                (array) ($options['description_consolidation'] ?? []),
                (array) ($options['actor_resolution'] ?? []),
                (array) ($options['title_fallback'] ?? []), $context);
            $this->processRow($runId, $profileId, $row, $plan);
            $run = $repository->get($runId) ?? $run;
        }
        $run = $repository->get($runId) ?? $run;
        if ((int) $run['current_offset'] >= (int) $run['total_rows']) {
            $repository->update($runId, [
                'status' => (int) $run['failed_count'] > 0 || (int) $run['changed_count'] > 0 ? 'completed_with_issues' : 'completed',
                'finished_at' => $this->now(),
            ]);
        }
        return $repository->get($runId) ?? $run;
    }

    private function processRow(int $runId, int $profileId, SourceRow $row, MigrationPlan $plan): void
    {
        global $DB;
        $run = (new RunRepository())->get($runId);
        if ($run === null) { throw new \RuntimeException('Migration run not found.'); }
        $hash = (new CanonicalRowHasher())->hash($row->values);
        $externalId = trim((string) ($plan->externalReference['external_id'] ?? ''));
        $existingItem = $DB->request(['FROM' => 'glpi_plugin_ticketmigration_runitems', 'WHERE' => ['runs_id' => $runId, 'row_number' => $row->number], 'LIMIT' => 1])->current();
        if ($existingItem !== false) {
            $this->advance($run, []);
            return;
        }
        $status = 'success'; $ticketId = null; $message = 'ticket_imported'; $failure = null;
        $existing = $externalId !== '' ? (new ImportLedgerRepository())->find($profileId, $externalId) : null;
        if (!$plan->isExecutable()) { $status = 'failed'; $message = 'plan_blocked'; }
        elseif ($existing !== null) {
            $ticketId = (int) $existing['tickets_id'];
            $status = hash_equals((string) $existing['source_hash'], $hash) ? 'skipped' : 'changed';
            $message = $status === 'skipped' ? 'already_imported' : 'source_changed_after_import';
        } elseif (!\Session::haveAccessToEntity((int) ($plan->ticket['entity']['id'] ?? -1))) {
            $status = 'failed'; $message = 'target_entity_forbidden';
        } else {
            try { $ticketId = (new GlpiTicketExecutor())->execute($plan); }
            catch (\Throwable $exception) { ErrorHandler::logCaughtException($exception); $status = 'failed'; $message = 'ticket_creation_failed'; $failure = $exception; }
        }
        $now = $this->now();
        $DB->beginTransaction();
        try {
            $DB->insert('glpi_plugin_ticketmigration_runitems', [
                'runs_id' => $runId, 'row_number' => $row->number, 'external_id' => $externalId ?: null,
                'source_hash' => $hash, 'status' => $status, 'tickets_id' => $ticketId, 'message' => $message,
                'warnings' => json_encode($plan->warnings, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'information' => json_encode($plan->information, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'validations' => json_encode($plan->validations, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'errors' => json_encode($failure ? array_merge($plan->errors, [$failure->getMessage()]) : $plan->errors, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'started_at' => $now, 'finished_at' => $now,
            ]);
            if ($status === 'success') {
                $DB->insert('glpi_plugin_ticketmigration_externalrefs', [
                    'profiles_id' => $profileId, 'external_id' => $externalId, 'tickets_id' => $ticketId,
                    'source_hash' => $hash, 'source_updated_at' => null, 'imported_at' => $now, 'runs_id' => $runId,
                ]);
            }
            $increments = ['processed_rows' => 1, 'current_offset' => 1];
            $increments[match ($status) { 'success' => 'success_count', 'skipped' => 'skipped_count', 'changed' => 'changed_count', default => 'failed_count' }] = 1;
            if ($plan->warnings !== []) { $increments['warning_count'] = count($plan->warnings); }
            $this->advance($run, $increments);
            $DB->commit();
        } catch (\Throwable $exception) { $DB->rollBack(); throw $exception; }
    }

    private function advance(array $run, array $increments): void
    {
        $values = [];
        foreach ($increments as $field => $increment) { $values[$field] = (int) $run[$field] + $increment; }
        if ($values !== []) { (new RunRepository())->update((int) $run['id'], $values); }
    }

    private function now(): string { return $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s'); }
}
