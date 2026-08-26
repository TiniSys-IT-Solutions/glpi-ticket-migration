<?php

namespace GlpiPlugin\Ticketmigration\Execution;

use GlpiPlugin\Ticketmigration\Idempotency\CanonicalRowHasher;
use GlpiPlugin\Ticketmigration\MigrationProfile;
use GlpiPlugin\Ticketmigration\Plan\MigrationPlan;
use GlpiPlugin\Ticketmigration\Source\SourceRow;
use GlpiPlugin\Ticketmigration\SourceFile;

final class PilotImportService
{
    public function execute(MigrationProfile $profile, SourceFile $source, SourceRow $row, MigrationPlan $plan): PilotImportResult
    {
        global $DB;
        $profileId = (int) $profile->getID();
        $externalId = trim((string) ($plan->externalReference['external_id'] ?? ''));
        if ($externalId === '' || !$plan->isExecutable()) {
            throw new \InvalidArgumentException('The pilot plan is not executable.');
        }
        $ledger = new ImportLedgerRepository();
        if (($existing = $ledger->find($profileId, $externalId)) !== null) {
            return new PilotImportResult((int) $existing['tickets_id'], (int) $existing['runs_id'], true);
        }
        // Keep the advisory-lock name well below MySQL's historical 64-byte
        // limit after GLPI prefixes it with the database name.
        $lockName = 'tm_pilot_' . substr(hash('sha256', $profileId . "\0" . $externalId), 0, 32);
        if (!$DB->getLock($lockName)) {
            if (($existing = $ledger->find($profileId, $externalId)) !== null) {
                return new PilotImportResult((int) $existing['tickets_id'], (int) $existing['runs_id'], true);
            }
            return new PilotImportResult(0, 0, false, true);
        }
        try {
            return $this->executeWithLock($profile, $source, $row, $plan, $externalId);
        } finally {
            $DB->releaseLock($lockName);
        }
    }

    private function executeWithLock(MigrationProfile $profile, SourceFile $source, SourceRow $row, MigrationPlan $plan, string $externalId): PilotImportResult
    {
        global $DB;
        $profileId = (int) $profile->getID();
        $ledger = new ImportLedgerRepository();
        if (($existing = $ledger->find($profileId, $externalId)) !== null) {
            return new PilotImportResult((int) $existing['tickets_id'], (int) $existing['runs_id'], true);
        }
        $rowHash = (new CanonicalRowHasher())->hash($row->values);
        $now = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $DB->insert('glpi_plugin_ticketmigration_runs', [
            'profiles_id' => $profileId,
            'users_id' => (int) \Session::getLoginUserID(),
            'entities_id' => (int) $plan->ticket['entity']['id'],
            'source_filename' => (string) $source->fields['source_filename'],
            'source_internal_name' => (string) $source->fields['internal_filename'],
            'source_hash' => (string) $source->fields['sha256'],
            'status' => 'running',
            'mode' => 'pilot',
            'started_at' => $now,
            'total_rows' => 1,
            'current_offset' => max(0, $row->number - 2),
        ]);
        $runId = (int) $DB->insertId();
        $DB->beginTransaction();
        try {
            $ticketId = (new GlpiTicketExecutor())->execute($plan);
            $DB->insert('glpi_plugin_ticketmigration_runitems', [
                'runs_id' => $runId,
                'row_number' => $row->number,
                'external_id' => $externalId,
                'source_hash' => $rowHash,
                'status' => 'success',
                'tickets_id' => $ticketId,
                'message' => 'pilot_import',
                'warnings' => json_encode($plan->warnings, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'information' => json_encode($plan->information, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'validations' => json_encode($plan->validations, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'errors' => json_encode([], JSON_THROW_ON_ERROR),
                'started_at' => $now,
                'finished_at' => $now,
            ]);
            $DB->insert('glpi_plugin_ticketmigration_externalrefs', [
                'profiles_id' => $profileId,
                'external_id' => $externalId,
                'tickets_id' => $ticketId,
                'source_hash' => $rowHash,
                'source_updated_at' => null,
                'imported_at' => $now,
                'runs_id' => $runId,
            ]);
            $DB->update('glpi_plugin_ticketmigration_runs', [
                'status' => 'completed',
                'finished_at' => $now,
                'processed_rows' => 1,
                'success_count' => 1,
                'warning_count' => count($plan->warnings),
                'current_offset' => max(0, $row->number - 1),
            ], ['id' => $runId]);
            $DB->commit();
            return new PilotImportResult($ticketId, $runId);
        } catch (\Throwable $exception) {
            $DB->rollBack();
            $DB->insert('glpi_plugin_ticketmigration_runitems', [
                'runs_id' => $runId,
                'row_number' => $row->number,
                'external_id' => $externalId,
                'source_hash' => $rowHash,
                'status' => 'failed',
                'tickets_id' => null,
                'message' => 'pilot_creation_failed',
                'warnings' => json_encode($plan->warnings, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'information' => json_encode($plan->information, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'validations' => json_encode($plan->validations, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                'errors' => json_encode(['pilot_creation_failed'], JSON_THROW_ON_ERROR),
                'started_at' => $now,
                'finished_at' => $now,
            ]);
            $DB->update('glpi_plugin_ticketmigration_runs', [
                'status' => 'failed',
                'finished_at' => $now,
                'processed_rows' => 1,
                'failed_count' => 1,
            ], ['id' => $runId]);
            throw $exception;
        }
    }
}
