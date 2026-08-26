<?php

namespace GlpiPlugin\Ticketmigration\Plan;

use GlpiPlugin\Ticketmigration\Mapping\DistinctValueCollector;
use GlpiPlugin\Ticketmigration\Mapping\TargetRegistry;
use GlpiPlugin\Ticketmigration\Source\SourceRow;

final class MigrationPlanBuilder
{
    public function build(SourceRow $row, array $fieldMappings, array $valueMappings, array $columns = [], array $descriptionConfiguration = [], array $resolutionConfiguration = [], array $titleFallbackConfiguration = [], array $entityContext = []): MigrationPlan
    {
        $ticket = [];
        $actors = [];
        $external = [];
        $information = [];
        $validations = [];
        $warnings = [];
        $errors = [];
        foreach ($fieldMappings as $index => $mapping) {
            $target = (string) ($mapping['target_key'] ?? '');
            if ($target === '' || $mapping['strategy'] === 'ignore') {
                continue;
            }
            $rawValue = trim((string) $row->value((int) $index));
            $mappingConfiguration = json_decode((string) ($mapping['configuration'] ?? ''), true) ?: [];
            $sourceValues = str_starts_with($target, 'actor.')
                ? (new DistinctValueCollector())->splitValue($rawValue, $mappingConfiguration['multi_delimiter'] ?? 'auto')
                : [$rawValue];
            foreach ($sourceValues as $sourceValue) {
                if ($sourceValue === '') {
                    continue;
                }
                $value = $sourceValue;
                $definition = TargetRegistry::definitions()[$target] ?? [];
                if (isset($definition['value_kind'])) {
                    $resolved = $valueMappings[$target][hash('sha256', $sourceValue)] ?? null;
                    if ($resolved === null) {
                        if (in_array($target, (array) ($resolutionConfiguration['skip_unresolved_targets'] ?? []), true)) {
                            $warnings[] = sprintf(__('Unresolved actor "%s" omitted for %s.', 'ticketmigration'), $sourceValue, $target);
                            continue;
                        }
                        $errors[] = sprintf(__('Unresolved value "%s" for %s.', 'ticketmigration'), $sourceValue, $target);
                        continue;
                    }
                    if ($resolved['target_value'] === '__ignore__') {
                        $information[] = sprintf(__('Value "%s" ignored for %s.', 'ticketmigration'), $sourceValue, $target);
                        continue;
                    }
                    $value = $resolved['target_itemtype']
                        ? ['itemtype' => $resolved['target_itemtype'], 'id' => (int) $resolved['target_id']]
                        : $resolved['target_value'];
                }
                if ($target === 'ticket.external_id') {
                    $external['external_id'] = $sourceValue;
                } elseif (str_starts_with($target, 'ticket.')) {
                    if (in_array($target, ['ticket.date', 'ticket.solvedate', 'ticket.closedate'], true)) {
                        $normalizedDate = (new DateNormalizer())->normalize((string) $value);
                        if ($normalizedDate === null) {
                            $errors[] = sprintf(__('Invalid date "%s" for %s.', 'ticketmigration'), $sourceValue, $target);
                            continue;
                        }
                        $value = $normalizedDate;
                    }
                    $ticket[substr($target, 7)] = $value;
                } elseif (str_starts_with($target, 'actor.')) {
                    $actors[substr($target, 6)][] = $value;
                }
            }
        }
        if (($external['external_id'] ?? '') === '') {
            $errors[] = __('External ticket identifier is empty.', 'ticketmigration');
        }
        $this->resolveEntity($ticket, $actors, $entityContext, $warnings, $information, $validations);
        if (($ticket['name'] ?? '') === '') {
            if ((bool) ($titleFallbackConfiguration['enabled'] ?? true)) {
                $ticket['name'] = $this->fallbackTitle(
                    (string) ($ticket['content'] ?? ''),
                    (string) ($external['external_id'] ?? ''),
                    max(3, min(30, (int) ($titleFallbackConfiguration['word_count'] ?? 12))),
                );
                $validations[] = __('The empty ticket title was generated from the description or external identifier.', 'ticketmigration');
            } else {
                $errors[] = __('Ticket title is empty.', 'ticketmigration');
            }
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
        return new MigrationPlan(ticket: $ticket, actors: $actors, externalReference: $external, information: $information, validations: $validations, warnings: $warnings, errors: $errors);
    }

    private function fallbackTitle(string $description, string $externalId, int $wordCount): string
    {
        $plainDescription = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($description), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
        if ($plainDescription !== '') {
            $words = preg_split('/\s+/u', $plainDescription) ?: [];
            $excerpt = implode(' ', array_slice($words, 0, $wordCount));
            if (count($words) > $wordCount) {
                $excerpt .= '…';
            }
            return mb_strimwidth(__('Ticket', 'ticketmigration') . ' — ' . $excerpt, 0, 250, '…', 'UTF-8');
        }
        return mb_strimwidth(__('Ticket', 'ticketmigration') . ($externalId !== '' ? ' ' . $externalId : ''), 0, 250, '…', 'UTF-8');
    }

    private function resolveEntity(array &$ticket, array $actors, array $context, array &$warnings, array &$information, array &$validations): void
    {
        if (isset($ticket['entity']['id'])) {
            return;
        }
        $requesterEntities = [];
        $preferredRequesterEntities = [];
        foreach ((array) ($actors['requester'] ?? []) as $requester) {
            if (($requester['itemtype'] ?? '') === 'User') {
                $requesterId = (int) $requester['id'];
                $requesterEntities = array_merge($requesterEntities, (array) (($context['user_entities'] ?? [])[$requesterId] ?? []));
                if (isset(($context['user_preferred_entities'] ?? [])[$requesterId])) {
                    $preferredRequesterEntities[] = (int) $context['user_preferred_entities'][$requesterId];
                }
            }
        }
        $requesterEntities = array_values(array_unique(array_map('intval', $requesterEntities)));
        $preferredRequesterEntities = array_values(array_unique($preferredRequesterEntities));
        $requesterEntityId = count($preferredRequesterEntities) === 1
            ? $preferredRequesterEntities[0]
            : (count($requesterEntities) === 1 ? $requesterEntities[0] : null);
        $locationId = (int) ($ticket['location']['id'] ?? 0);
        $locationEntityId = $locationId > 0 && array_key_exists($locationId, (array) ($context['location_entities'] ?? []))
            ? (int) $context['location_entities'][$locationId]
            : null;
        if ($requesterEntityId !== null) {
            $ticket['entity'] = ['itemtype' => 'Entity', 'id' => $requesterEntityId];
            $validations[] = count($preferredRequesterEntities) === 1
                ? __('Ticket entity derived from the requester preferred GLPI entity.', 'ticketmigration')
                : __('Ticket entity derived from the requester unique GLPI authorization.', 'ticketmigration');
            if ($locationEntityId !== null && $locationEntityId !== $requesterEntityId) {
                $information[] = __('The resolved location points to another entity; the requester entity took precedence.', 'ticketmigration');
            }
            return;
        }
        if (count($requesterEntities) > 1) {
            $warnings[] = __('Requester has several GLPI entity authorizations without one usable preference.', 'ticketmigration');
        }
        if ($locationEntityId !== null) {
            $ticket['entity'] = ['itemtype' => 'Entity', 'id' => $locationEntityId];
            $source = (string) (($context['location_entity_sources'] ?? [])[$locationId] ?? 'ownership');
            if ($source === 'profile_mapping') {
                $validations[] = __('Ticket entity derived from the migration profile location/entity mapping.', 'ticketmigration');
            } else {
                $information[] = $source === 'hierarchy_name'
                    ? __('Ticket entity derived from an exact match with the resolved location hierarchy.', 'ticketmigration')
                    : __('Ticket entity derived from the resolved location.', 'ticketmigration');
            }
            return;
        }
        $ticket['entity'] = ['itemtype' => 'Entity', 'id' => max(0, (int) ($context['default_entity_id'] ?? 0))];
        $information[] = __('Ticket entity set to the migration profile default entity.', 'ticketmigration');
    }
}
