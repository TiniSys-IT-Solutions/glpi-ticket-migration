<?php

namespace GlpiPlugin\Ticketmigration\Execution;

final class ImportLedgerRepository
{
    public static function lockName(int $profileId): string
    {
        // Pilot and final imports must share a lock, including different runs.
        return 'tm_import_' . substr(hash('sha256', (string) $profileId), 0, 32);
    }

    public function ticketAvailable(array $reference): bool
    {
        $ticket = new \Ticket();
        return $ticket->getFromDB((int) $reference['tickets_id'])
            && empty($ticket->fields['is_deleted'])
            && $ticket->can((int) $reference['tickets_id'], READ);
    }

    public function find(int $profileId, string $externalId): ?array
    {
        global $DB;
        $row = $DB->request([
            'FROM' => 'glpi_plugin_ticketmigration_externalrefs',
            'WHERE' => ['profiles_id' => $profileId, 'external_id' => $externalId],
            'LIMIT' => 1,
        ])->current();
        return is_array($row) ? $row : null;
    }

    public function shouldSkip(int $profileId, string $externalId, string $sourceHash): bool
    {
        $existing = $this->find($profileId, $externalId);
        return $existing !== null && hash_equals((string) $existing['source_hash'], $sourceHash);
    }
}
