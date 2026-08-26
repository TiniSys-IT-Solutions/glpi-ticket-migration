<?php

namespace GlpiPlugin\Ticketmigration\Plan;

final class EntityContextProvider
{
    public function build(int $defaultEntityId, array $valueMappings, array $locationEntityMappings = []): array
    {
        global $DB;
        $locationEntities = [];
        $locationEntitySources = [];
        $userEntities = [];
        $userPreferredEntities = [];
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
                        $locationEntityId = (int) $location->fields['entities_id'];
                        if (isset($locationEntityMappings[$id])) {
                            $locationEntities[$id] = (int) $locationEntityMappings[$id];
                            $locationEntitySources[$id] = 'profile_mapping';
                        } elseif ($locationEntityId > 0 || $defaultEntityId === 0) {
                            $locationEntities[$id] = $locationEntityId;
                            $locationEntitySources[$id] = 'ownership';
                        } elseif (($matchedEntityId = $this->matchLocationHierarchyToEntity($id, $defaultEntityId)) !== null) {
                            $locationEntities[$id] = $matchedEntityId;
                            $locationEntitySources[$id] = 'hierarchy_name';
                        }
                    }
                } elseif ($itemtype === 'User' && !array_key_exists($id, $userEntities)) {
                    $entities = array_values(array_unique(array_map(
                        'intval',
                        \Profile_User::getUserEntities($id, false, true),
                    )));
                    $entities = array_values(array_filter(
                        $entities,
                        static fn (int $entityId): bool => \Session::haveAccessToEntity($entityId),
                    ));
                    $eligibleEntities = array_values(array_unique(array_map(
                        'intval',
                        \Profile_User::getUserEntities($id, true, true),
                    )));
                    $eligibleEntities = array_values(array_filter(
                        $eligibleEntities,
                        static fn (int $entityId): bool => \Session::haveAccessToEntity($entityId),
                    ));
                    $user = new \User();
                    if ($user->getFromDB($id)) {
                        $preferred = (int) $user->fields['entities_id'];
                        if (in_array($preferred, $eligibleEntities, true)) {
                            $userPreferredEntities[$id] = $preferred;
                        } elseif (count($entities) === 1) {
                            $userPreferredEntities[$id] = $entities[0];
                        }
                    }
                    $userEntities[$id] = $entities;
                }
            }
        }
        return [
            'default_entity_id' => $defaultEntityId,
            'location_entities' => $locationEntities,
            'location_entity_sources' => $locationEntitySources,
            'user_entities' => $userEntities,
            'user_preferred_entities' => $userPreferredEntities,
        ];
    }

    private function matchLocationHierarchyToEntity(int $locationId, int $defaultEntityId): ?int
    {
        global $DB;

        if ($defaultEntityId <= 0) {
            return null;
        }

        $locationAncestors = getAncestorsOf('glpi_locations', $locationId);
        $locationIds = array_values(array_filter(array_unique(array_merge(
            [$locationId],
            array_map('intval', array_keys($locationAncestors)),
            array_map('intval', array_values($locationAncestors)),
        )), static fn (int $id): bool => $id > 0));
        $locationNames = [];
        foreach ($DB->request([
            'SELECT' => ['name'],
            'FROM' => 'glpi_locations',
            'WHERE' => ['id' => $locationIds],
        ]) as $location) {
            $normalized = $this->normalizeName((string) $location['name']);
            if ($normalized !== '') {
                $locationNames[$normalized] = true;
            }
        }
        if ($locationNames === []) {
            return null;
        }

        $entityDescendants = getSonsOf('glpi_entities', $defaultEntityId);
        $entityIds = array_values(array_filter(array_unique(array_merge(
            array_map('intval', array_keys($entityDescendants)),
            array_map('intval', array_values($entityDescendants)),
        )), static fn (int $id): bool => $id > 0 && $id !== $defaultEntityId));
        $matches = [];
        foreach ($DB->request([
            'SELECT' => ['id', 'name'],
            'FROM' => 'glpi_entities',
            'WHERE' => ['id' => $entityIds],
        ]) as $entity) {
            if (isset($locationNames[$this->normalizeName((string) $entity['name'])])) {
                $matches[] = (int) $entity['id'];
            }
        }
        $matches = array_values(array_unique($matches));

        return count($matches) === 1 ? $matches[0] : null;
    }

    private function normalizeName(string $name): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name) ?? ''), 'UTF-8');
    }
}
