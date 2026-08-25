<?php

namespace GlpiPlugin\Ticketmigration\Mapping;

final class FieldMappingRepository
{
    private const TABLE = 'glpi_plugin_ticketmigration_fieldmappings';

    public function forProfile(int $profileId): array
    {
        global $DB;
        $result = [];
        foreach ($DB->request(['FROM' => self::TABLE, 'WHERE' => ['profiles_id' => $profileId]]) as $row) {
            $result[(int) $row['source_index']] = $row;
        }
        return $result;
    }

    public function replace(int $profileId, array $columns, array $targets): bool
    {
        global $DB;
        $DB->beginTransaction();
        try {
            $DB->delete(self::TABLE, ['profiles_id' => $profileId]);
            $selectedTargets = [];
            foreach ($columns as $column) {
                $index = (int) $column->index;
                $target = trim((string) ($targets[$index] ?? ''));
                if ($target !== '' && !TargetRegistry::has($target)) {
                    throw new \InvalidArgumentException('Unsupported mapping target.');
                }
                if ($target !== '' && isset($selectedTargets[$target])) {
                    throw new \InvalidArgumentException('A target can only be mapped once.');
                }
                $selectedTargets[$target] = true;
                $DB->insert(self::TABLE, [
                    'profiles_id' => $profileId,
                    'source_index' => $index,
                    'source_name' => $column->name,
                    'target_key' => $target !== '' ? $target : null,
                    'strategy' => $target !== '' ? 'direct' : 'ignore',
                    'configuration' => null,
                    'sort_order' => $index,
                ]);
            }
            $DB->commit();
            return true;
        } catch (\Throwable $exception) {
            $DB->rollBack();
            throw $exception;
        }
    }

    public function missingRequiredTargets(int $profileId): array
    {
        $selected = array_column($this->forProfile($profileId), 'target_key');
        return array_values(array_diff(TargetRegistry::requiredKeys(), $selected));
    }
}
