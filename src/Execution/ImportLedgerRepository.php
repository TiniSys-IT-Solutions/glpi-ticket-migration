<?php

namespace GlpiPlugin\Ticketmigration\Execution;

final class ImportLedgerRepository
{
    public function find(int $profileId, string $externalId): ?array
    {
        global $DB;
        $row = $DB->request([
            'FROM' => 'glpi_plugin_ticketmigration_externalrefs',
            'WHERE' => ['profiles_id' => $profileId, 'external_id' => $externalId],
            'LIMIT' => 1,
        ])->current();
        return $row === false ? null : $row;
    }

    public function shouldSkip(int $profileId, string $externalId, string $sourceHash): bool
    {
        $existing = $this->find($profileId, $externalId);
        return $existing !== null && hash_equals((string) $existing['source_hash'], $sourceHash);
    }
}
