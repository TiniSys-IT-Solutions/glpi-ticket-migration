<?php

namespace GlpiPlugin\Ticketmigration\Plan;

use GlpiPlugin\Ticketmigration\Source\SourceColumn;
use GlpiPlugin\Ticketmigration\Source\SourceRow;

final class StructuredDescriptionBuilder
{
    public function build(SourceRow $row, array $columns, array $fieldMappings, string $mainDescription, array $configuration): string
    {
        if (!(bool) ($configuration['enabled'] ?? true)) {
            return $mainDescription;
        }
        $includeMapped = (bool) ($configuration['include_mapped'] ?? true);
        $includeUnmapped = (bool) ($configuration['include_unmapped'] ?? true);
        $excluded = array_map('intval', (array) ($configuration['excluded_columns'] ?? []));
        $metadata = [];
        foreach ($columns as $column) {
            if (!$column instanceof SourceColumn || in_array($column->index, $excluded, true)) {
                continue;
            }
            $mapping = $fieldMappings[$column->index] ?? null;
            $target = (string) ($mapping['target_key'] ?? '');
            $mapped = $target !== '' && ($mapping['strategy'] ?? 'ignore') !== 'ignore';
            if ($target === 'ticket.content' || ($mapped && !$includeMapped) || (!$mapped && !$includeUnmapped)) {
                continue;
            }
            $value = trim((string) $row->value($column->index));
            if ($value === '') {
                continue;
            }
            $metadata[] = sprintf(
                '<div><strong>%s:</strong> %s</div>',
                htmlspecialchars($column->name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
                nl2br(htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')),
            );
        }
        $metadataHtml = $metadata === [] ? '' : '<h2>'
            . __('Imported historical data', 'ticketmigration')
            . '</h2>' . implode('', $metadata);
        $descriptionHtml = trim($mainDescription) === '' ? '' : '<h2>'
            . __('Original description', 'ticketmigration')
            . '</h2><div>' . nl2br(htmlspecialchars($mainDescription, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')) . '</div>';
        if ($metadataHtml === '') {
            return $descriptionHtml;
        }
        if ($descriptionHtml === '') {
            return $metadataHtml;
        }
        return ($configuration['position'] ?? 'before') === 'after'
            ? $descriptionHtml . '<hr>' . $metadataHtml
            : $metadataHtml . '<hr>' . $descriptionHtml;
    }
}
