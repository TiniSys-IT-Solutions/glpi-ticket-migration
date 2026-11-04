<?php

namespace GlpiPlugin\Ticketmigration\Execution;

use GlpiPlugin\Ticketmigration\Idempotency\CanonicalRowHasher;
use GlpiPlugin\Ticketmigration\MigrationProfile;
use GlpiPlugin\Ticketmigration\ProfileRight;
use GlpiPlugin\Ticketmigration\Plan\MigrationPlan;
use GlpiPlugin\Ticketmigration\Source\SourceRow;
use GlpiPlugin\Ticketmigration\SourceFile;

final class PilotImportService
{
    public function execute(MigrationProfile $profile, SourceFile $source, SourceRow $row, MigrationPlan $plan): PilotImportResult
    {
        global $DB;
        if (!ProfileRight::canRunImports() || !\Ticket::canCreate() || !$profile->canViewItem()
            || !$source->canViewItem() || !empty($profile->fields['is_archived'])
            || (int) $source->fields['profiles_id'] !== (int) $profile->getID()
            || !\Session::haveAccessToEntity((int) ($plan->ticket['entity']['id'] ?? -1))) {
            throw new \RuntimeException('Import access denied.');
        }
        $profileId = (int) $profile->getID();
        $externalId = trim((string) ($plan->externalReference['external_id'] ?? ''));
        if ($externalId === '' || !$plan->isExecutable()) {
            throw new \InvalidArgumentException('The pilot plan is not executable.');
        }
        $ledger = new ImportLedgerRepository();
        if (($existing = $ledger->find($profileId, $externalId)) !== null) {
            return $this->existingResult($ledger, $existing);
        }
        $lockName = ImportLedgerRepository::lockName($profileId);
        if (!$DB->getLock($lockName)) {
            if (($existing = $ledger->find($profileId, $externalId)) !== null) {
                return $this->existingResult($ledger, $existing);
            }
            return new PilotImportResult(0, 0, false, true);
        }
        try {
            return $this->executeWithLock($profile, $source, $row, $plan, $externalId);
        } finally {
            $DB->releaseLock($lockName);
        }
    }

    private function existingResult(ImportLedgerRepository $ledger, array $existing): PilotImportResult
    {
        if (!$ledger->ticketAvailable($existing)) {
            throw new \RuntimeException('The imported ticket is unavailable; its reference is retained.');
        }
        return new PilotImportResult((int) $existing['tickets_id'], (int) $existing['runs_id'], true);
    }

    private function executeWithLock(MigrationProfile $profile, SourceFile $source, SourceRow $row, MigrationPlan $plan, string $externalId): PilotImportResult
    {
        global $DB;
        $profileId = (int) $profile->getID();
        $ledger = new ImportLedgerRepository();
        if (($existing = $ledger->find($profileId, $externalId)) !== null) {
            return $this->existingResult($ledger, $existing);
        }
        $rowHash = (new CanonicalRowHasher())->hash($row->values);
        $now = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        if (!$DB->insert('glpi_plugin_ticketmigration_runs', [
            'profiles_id' => $profileId,
            'sourcefiles_id' => (int) $source->getID(),
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
        ])) {
            throw new \RuntimeException('Unable to persist pilot import history.');
        }
        $runId = (int) $DB->insertId();
        $DB->beginTransaction();
        try {
            $ticketId = (new GlpiTicketExecutor())->execute($plan);
            if (!$DB->insert('glpi_plugin_ticketmigration_runitems', [
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
            ])) {
                throw new \RuntimeException('Unable to persist pilot import history.');
            }
            if (!$DB->insert('glpi_plugin_ticketmigration_externalrefs', [
                'profiles_id' => $profileId,
                'external_id' => $externalId,
                'tickets_id' => $ticketId,
                'source_hash' => $rowHash,
                'source_updated_at' => null,
                'imported_at' => $now,
                'runs_id' => $runId,
            ])) {
                throw new \RuntimeException('Unable to persist pilot import history.');
            }
            if (!$DB->update('glpi_plugin_ticketmigration_runs', [
                'status' => 'completed',
                'finished_at' => $now,
                'processed_rows' => 1,
                'success_count' => 1,
                'warning_count' => count($plan->warnings),
                'current_offset' => max(0, $row->number - 1),
            ], ['id' => $runId])) {
                throw new \RuntimeException('Unable to persist pilot import history.');
            }
            $DB->commit();
            return new PilotImportResult($ticketId, $runId);
        } catch (\Throwable $exception) {
            $DB->rollBack();
            if (!$DB->insert('glpi_plugin_ticketmigration_runitems', [
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
            ])) {
                throw new \RuntimeException('Unable to persist pilot import history.');
            }
            if (!$DB->update('glpi_plugin_ticketmigration_runs', [
                'status' => 'failed',
                'finished_at' => $now,
                'processed_rows' => 1,
                'failed_count' => 1,
            ], ['id' => $runId])) {
                throw new \RuntimeException('Unable to persist pilot import history.');
            }
            throw $exception;
        }
    }
}
