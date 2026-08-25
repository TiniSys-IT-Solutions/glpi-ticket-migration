<?php

namespace GlpiPlugin\Ticketmigration\Plan;

use GlpiPlugin\Ticketmigration\Mapping\DistinctValueCollector;
use GlpiPlugin\Ticketmigration\Mapping\TargetRegistry;
use GlpiPlugin\Ticketmigration\Source\SourceRow;

final class MigrationPlanBuilder
{
    public function build(SourceRow $row, array $fieldMappings, array $valueMappings, array $columns = [], array $descriptionConfiguration = [], array $resolutionConfiguration = [], array $titleFallbackConfiguration = []): MigrationPlan
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
                    $actors[substr($target, 6)][] = $value;
                }
            }
        }
        if (($external['external_id'] ?? '') === '') {
            $errors[] = __('External ticket identifier is empty.', 'ticketmigration');
        }
        if (($ticket['name'] ?? '') === '') {
            if ((bool) ($titleFallbackConfiguration['enabled'] ?? true)) {
                $ticket['name'] = $this->fallbackTitle(
                    (string) ($ticket['content'] ?? ''),
                    (string) ($external['external_id'] ?? ''),
                    max(3, min(30, (int) ($titleFallbackConfiguration['word_count'] ?? 12))),
                );
                $warnings[] = __('The empty ticket title was generated from the description or external identifier.', 'ticketmigration');
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
        return new MigrationPlan(ticket: $ticket, actors: $actors, externalReference: $external, warnings: $warnings, errors: $errors);
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
}
