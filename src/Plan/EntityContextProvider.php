<?php

namespace GlpiPlugin\Ticketmigration\Plan;

final class EntityContextProvider
{
    public function build(int $defaultEntityId, array $valueMappings): array
    {
        global $DB;
        $locationEntities = [];
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
                        $locationEntities[$id] = (int) $location->fields['entities_id'];
                    }
                } elseif ($itemtype === 'User' && !array_key_exists($id, $userEntities)) {
                    $entities = [];
                    foreach ($DB->request([
                        'SELECT' => ['entities_id'],
                        'DISTINCT' => true,
                        'FROM' => 'glpi_profiles_users',
                        'WHERE' => ['users_id' => $id],
                    ]) as $profileUser) {
                        $entities[] = (int) $profileUser['entities_id'];
                    }
                    $userEntities[$id] = array_values(array_unique($entities));
                }
            }
        }
        return ['default_entity_id' => $defaultEntityId, 'location_entities' => $locationEntities, 'user_entities' => $userEntities];
    }
}
