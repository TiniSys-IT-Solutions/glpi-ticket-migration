<?php

namespace GlpiPlugin\Ticketmigration\Execution;

use GlpiPlugin\Ticketmigration\MigrationProfile;
use GlpiPlugin\Ticketmigration\SourceFile;
use GlpiPlugin\Ticketmigration\Mapping\FieldMappingRepository;
use GlpiPlugin\Ticketmigration\Mapping\LocationEntityMappingRepository;
use GlpiPlugin\Ticketmigration\Mapping\ValueMappingRepository;
use GlpiPlugin\Ticketmigration\Plan\EntityContextProvider;

final class RunRepository
{
    private const TABLE = 'glpi_plugin_ticketmigration_runs';

    public function createFinal(MigrationProfile $profile, SourceFile $source, int $totalRows): int
    {
        global $DB;
        $now = $_SESSION['glpi_currenttime'] ?? date('Y-m-d H:i:s');
        $profileId = (int) $profile->getID();
        $valueMappings = (new ValueMappingRepository())->forProfile($profileId);
        $snapshot = [
            'field_mappings' => (new FieldMappingRepository())->forProfile($profileId),
            'value_mappings' => $valueMappings,
            'options' => json_decode((string) ($profile->fields['options'] ?? ''), true) ?: [],
            'entity_context' => (new EntityContextProvider())->build($profile->fields['entities_id'], $valueMappings, (new LocationEntityMappingRepository())->forProfile($profileId)),
        ];
        $DB->insert(self::TABLE, [
            'profiles_id' => (int) $profile->getID(), 'sourcefiles_id' => (int) $source->getID(),
            'users_id' => (int) \Session::getLoginUserID(), 'entities_id' => (int) $profile->fields['entities_id'],
            'source_filename' => (string) $source->fields['source_filename'],
            'source_internal_name' => (string) $source->fields['internal_filename'],
            'source_hash' => (string) $source->fields['sha256'],
            'configuration_snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'status' => 'queued', 'mode' => 'final',
            'backup_confirmed_at' => $now, 'backup_confirmed_by' => (int) \Session::getLoginUserID(),
            'total_rows' => $totalRows,
        ]);
        return (int) $DB->insertId();
    }

    public function get(int $id): ?array
    {
        global $DB;
        $row = $DB->request(['FROM' => self::TABLE, 'WHERE' => ['id' => $id], 'LIMIT' => 1])->current();
        return $row === false ? null : $row;
    }

    public function findActiveFinal(int $profileId): ?array
    {
        global $DB;
        $row = $DB->request([
            'FROM' => self::TABLE,
            'WHERE' => ['profiles_id' => $profileId, 'mode' => 'final', 'status' => ['queued', 'running', 'paused']],
            'ORDER' => ['id DESC'], 'LIMIT' => 1,
        ])->current();
        return $row === false ? null : $row;
    }

    public function update(int $id, array $values): void
    {
        global $DB;
        $DB->update(self::TABLE, $values, ['id' => $id]);
    }

    public function recentItems(int $runId, int $limit = 50): array
    {
        global $DB;
        return iterator_to_array($DB->request([
            'FROM' => 'glpi_plugin_ticketmigration_runitems', 'WHERE' => ['runs_id' => $runId],
            'ORDER' => ['row_number DESC'], 'LIMIT' => $limit,
        ]));
    }

    public function list(int $limit = 100): array
    {
        global $DB;
        return iterator_to_array($DB->request(['FROM' => self::TABLE, 'ORDER' => ['id DESC'], 'LIMIT' => $limit]));
    }
}
