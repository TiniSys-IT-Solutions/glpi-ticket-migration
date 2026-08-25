<?php

namespace GlpiPlugin\Ticketmigration\Plan;

use GlpiPlugin\Ticketmigration\Source\SourceRow;

final class MigrationPlanBuilder
{
    public function build(SourceRow $row, array $fieldMappings, array $valueMappings, array $columns = [], array $descriptionConfiguration = []): MigrationPlan
    {
        $ticket = [];
        $actors = [];
        $external = [];
        $warnings = [];
        $errors = [];
        foreach ($fieldMappings as $index => $mapping) {
            $target = (string) ($mapping['target_key'] ?? '');
            if ($target === '' || $mapping['strategy'] === 'ignore') {
                continue;
            }
            $sourceValue = trim((string) $row->value((int) $index));
            if ($sourceValue === '') {
                continue;
            }
            $value = $sourceValue;
            if (isset($valueMappings[$target])) {
                $resolved = $valueMappings[$target][hash('sha256', $sourceValue)] ?? null;
                if ($resolved === null) {
                    $errors[] = sprintf(__('Unresolved value "%s" for %s.', 'ticketmigration'), $sourceValue, $target);
                    continue;
                }
                if ($resolved['target_value'] === '__ignore__') {
                    $warnings[] = sprintf(__('Value "%s" ignored for %s.', 'ticketmigration'), $sourceValue, $target);
                    continue;
                }
                $value = $resolved['target_itemtype']
                    ? ['itemtype' => $resolved['target_itemtype'], 'id' => (int) $resolved['target_id']]
                    : $resolved['target_value'];
            }
            if ($target === 'ticket.external_id') {
                $external['external_id'] = $sourceValue;
            } elseif (str_starts_with($target, 'ticket.')) {
                $ticket[substr($target, 7)] = $value;
            } elseif (str_starts_with($target, 'actor.')) {
                $actors[substr($target, 6)] = $value;
            }
        }
        if (($external['external_id'] ?? '') === '') {
            $errors[] = __('External ticket identifier is empty.', 'ticketmigration');
        }
        if (($ticket['name'] ?? '') === '') {
            $errors[] = __('Ticket title is empty.', 'ticketmigration');
        }
        if ($columns !== [] && ($descriptionConfiguration['enabled'] ?? true)) {
            $ticket['content'] = (new StructuredDescriptionBuilder())->build(
                $row,
                $columns,
                $fieldMappings,
                (string) ($ticket['content'] ?? ''),
                $descriptionConfiguration,
            );
        }
        return new MigrationPlan(ticket: $ticket, actors: $actors, externalReference: $external, warnings: $warnings, errors: $errors);
    }
}
