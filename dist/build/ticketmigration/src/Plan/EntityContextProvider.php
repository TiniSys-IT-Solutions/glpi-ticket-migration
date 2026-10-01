<?php

namespace GlpiPlugin\Ticketmigration\Plan;

final class EntityContextProvider
{
    public function build(int $defaultEntityId, array $valueMappings, array $locationEntityMappings = []): array
    {
        global $DB;
        $allowedEntityIds = $this->projectEntityIds($defaultEntityId);
        $locationEntities = [];
        $locationEntitySources = [];
        $userEntities = [];
        foreach ($valueMappings as $entries) {
            foreach ($entries as $entry) {
                $itemtype = (string) ($entry['target_itemtype'] ?? '');
                $id = (int) ($entry['target_id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                if ($itemtype === 'Location' && !array_key_exists($id, $locationEntities)) {
                    $location = new \Location();
                    if ($location->getFromDB($id) && $location->canViewItem()) {
                        if (isset($locationEntityMappings[$id]) && in_array((int) $locationEntityMappings[$id], $allowedEntityIds, true)) {
                            $locationEntities[$id] = (int) $locationEntityMappings[$id];
                            $locationEntitySources[$id] = 'profile_mapping';
                        }
                    }
                } elseif ($itemtype === 'User' && !array_key_exists($id, $userEntities)) {
                    $entities = array_values(array_unique(array_map(
                        'intval',
                        \Profile_User::getUserEntities($id, false, true),
                    )));
                    $entities = array_values(array_filter(
                        $entities,
                        static fn (int $entityId): bool => in_array($entityId, $allowedEntityIds, true) && \Session::haveAccessToEntity($entityId),
                    ));
                    $userEntities[$id] = $entities;
                }
            }
        }
        return [
            'default_entity_id' => $defaultEntityId,
            'allowed_entity_ids' => $allowedEntityIds,
            'location_entities' => $locationEntities,
            'location_entity_sources' => $locationEntitySources,
            'user_entities' => $userEntities,
        ];
    }

    /** @return list<int> */
    private function projectEntityIds(int $defaultEntityId): array
    {
        $descendants = getSonsOf('glpi_entities', $defaultEntityId);
        return array_values(array_unique(array_map('intval', array_merge(
            [$defaultEntityId],
            array_keys($descendants),
            array_values($descendants),
        ))));
    }

}
