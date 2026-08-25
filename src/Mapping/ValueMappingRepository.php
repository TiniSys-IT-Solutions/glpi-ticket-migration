<?php

namespace GlpiPlugin\Ticketmigration\Mapping;

final class ValueMappingRepository
{
    private const TABLE = 'glpi_plugin_ticketmigration_valuemappings';

    public function forProfile(int $profileId): array
    {
        global $DB;
        $result = [];
        foreach ($DB->request(['FROM' => self::TABLE, 'WHERE' => ['profiles_id' => $profileId]]) as $row) {
            $result[(string) $row['mapping_key']][(string) $row['source_value_hash']] = $row;
        }
        return $result;
    }

    public function replace(int $profileId, array $decisions): void
    {
        global $DB;
        $DB->beginTransaction();
        try {
            $DB->delete(self::TABLE, ['profiles_id' => $profileId]);
            foreach ($decisions as $decision) {
                $DB->insert(self::TABLE, [
                    'profiles_id' => $profileId,
                    'mapping_key' => $decision['mapping_key'],
                    'source_value_hash' => hash('sha256', $decision['source_value']),
                    'source_value' => $decision['source_value'],
                    'target_itemtype' => $decision['target_itemtype'] ?: null,
                    'target_id' => $decision['target_id'] ?: null,
                    'target_value' => $decision['target_value'] !== '' ? $decision['target_value'] : null,
                ]);
            }
            $DB->commit();
        } catch (\Throwable $exception) {
            $DB->rollBack();
            throw $exception;
        }
    }
}
