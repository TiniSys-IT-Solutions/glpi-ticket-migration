<?php

namespace GlpiPlugin\Ticketmigration\Mapping;

final class LocationEntityMappingRepository
{
    private const TABLE = 'glpi_plugin_ticketmigration_locationentitymappings';

    /** @return array<int, int> */
    public function forProfile(int $profileId): array
    {
        global $DB;
        $mappings = [];
        foreach ($DB->request(['FROM' => self::TABLE, 'WHERE' => ['profiles_id' => $profileId]]) as $row) {
            $mappings[(int) $row['locations_id']] = (int) $row['entities_id'];
        }
        return $mappings;
    }

    public function merge(int $profileId, array $allowedLocationIds, array $submittedMappings): void
    {
        global $DB;
        $allowed = array_fill_keys(array_map('intval', $allowedLocationIds), true);
        $DB->beginTransaction();
        try {
            foreach ($submittedMappings as $locationId => $entityId) {
                $locationId = (int) $locationId;
                $entityId = (int) $entityId;
                if (!isset($allowed[$locationId])) {
                    throw new \InvalidArgumentException('Invalid location/entity mapping.');
                }
                $DB->delete(self::TABLE, ['profiles_id' => $profileId, 'locations_id' => $locationId]);
                if ($entityId > 0) {
                    $entity = new \Entity();
                    if (!$entity->getFromDB($entityId) || !$entity->canViewItem() || !\Session::haveAccessToEntity($entityId)) {
                        throw new \InvalidArgumentException('Invalid location/entity target.');
                    }
                    $DB->insert(self::TABLE, [
                        'profiles_id' => $profileId,
                        'locations_id' => $locationId,
                        'entities_id' => $entityId,
                    ]);
                }
            }
            $DB->commit();
        } catch (\Throwable $exception) {
            $DB->rollBack();
            throw $exception;
        }
    }
}
