<?php

namespace GlpiPlugin\Ticketmigration\Execution;

use GlpiPlugin\Ticketmigration\Plan\MigrationPlan;

final class TicketInputBuilder
{
    public function build(MigrationPlan $plan): array
    {
        $input = [
            '_auto_import' => true,
            '_disablenotif' => true,
            '_skip_auto_assign' => true,
            '_skip_default_contract' => true,
            '_skip_rules' => true,
            '_contracts_id' => 0,
            'externalid' => trim((string) ($plan->externalReference['external_id'] ?? '')),
        ];
        foreach (['name', 'content', 'date', 'closedate', 'solvedate', 'status', 'priority', 'urgency', 'impact', 'type'] as $field) {
            if (array_key_exists($field, $plan->ticket)) {
                $input[$field] = $plan->ticket[$field];
            }
        }
        $references = [
            'entity' => ['Entity', 'entities_id'],
            'location' => ['Location', 'locations_id'],
            'category' => ['ITILCategory', 'itilcategories_id'],
        ];
        foreach ($references as $planField => [$itemtype, $inputField]) {
            $reference = $plan->ticket[$planField] ?? null;
            if (is_array($reference) && ($reference['itemtype'] ?? '') === $itemtype && (int) ($reference['id'] ?? 0) >= 0) {
                $input[$inputField] = (int) $reference['id'];
            }
        }
        $actorFields = [
            'requester' => ['User', '_users_id_requester'],
            'assignee' => ['User', '_users_id_assign'],
            'requester_group' => ['Group', '_groups_id_requester'],
            'assignee_group' => ['Group', '_groups_id_assign'],
        ];
        foreach ($actorFields as $role => [$itemtype, $inputField]) {
            $ids = [];
            foreach ((array) ($plan->actors[$role] ?? []) as $actor) {
                if (($actor['itemtype'] ?? '') === $itemtype && (int) ($actor['id'] ?? 0) > 0) {
                    $ids[] = (int) $actor['id'];
                }
            }
            if ($ids !== []) {
                $input[$inputField] = array_values(array_unique($ids));
            }
        }
        return $input;
    }
}
