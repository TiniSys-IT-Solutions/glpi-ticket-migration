<?php

namespace GlpiPlugin\Ticketmigration\Source;

use GlpiPlugin\Ticketmigration\MigrationProfile;
use GlpiPlugin\Ticketmigration\ProfileRight;
use GlpiPlugin\Ticketmigration\SourceFile;

final class SourceRetentionService
{
    /** @return array{stored_count: int, stored_bytes: int, cleanup_count: int, cleanup_bytes: int} */
    public function report(): array
    {
        global $DB;
        $activeIds = [];
        foreach ($DB->request(['SELECT' => ['sourcefiles_id'], 'FROM' => MigrationProfile::getTable(), 'WHERE' => ['NOT' => ['sourcefiles_id' => null]]]) as $profile) {
            $activeIds[] = (int) $profile['sourcefiles_id'];
        }
        $referencedHashes = $this->referencedHashes();
        $policy = new SourceRetentionPolicy();
        $now = date('Y-m-d H:i:s');
        $storedCount = 0;
        $storedBytes = 0;
        $cleanupCount = 0;
        $cleanupBytes = 0;
        foreach ($DB->request(['FROM' => SourceFile::getTable(), 'WHERE' => ['deleted_at' => null]]) as $source) {
            $storedCount++;
            $storedBytes += (int) $source['filesize'];
            if ($policy->isCleanupCandidate($source, $activeIds, $referencedHashes, $now)) {
                $cleanupCount++;
                $cleanupBytes += (int) $source['filesize'];
            }
        }
        return [
            'stored_count' => $storedCount,
            'stored_bytes' => $storedBytes,
            'cleanup_count' => $cleanupCount,
            'cleanup_bytes' => $cleanupBytes,
        ];
    }

    public function cleanupExpired(): int
    {
        global $DB;
        $activeIds = [];
        foreach ($DB->request(['SELECT' => ['sourcefiles_id'], 'FROM' => MigrationProfile::getTable(), 'WHERE' => ['NOT' => ['sourcefiles_id' => null]]]) as $profile) {
            $activeIds[] = (int) $profile['sourcefiles_id'];
        }
        $referencedHashes = $this->referencedHashes();
        $policy = new SourceRetentionPolicy();
        $now = date('Y-m-d H:i:s');
        $deleted = 0;
        foreach ($DB->request(['FROM' => SourceFile::getTable(), 'WHERE' => ['deleted_at' => null]]) as $sourceData) {
            if (!$policy->isCleanupCandidate($sourceData, $activeIds, $referencedHashes, $now)) {
                continue;
            }
            $profile = new MigrationProfile();
            if (ProfileRight::canManageProfiles(DELETE)
                && $profile->getFromDB((int) $sourceData['profiles_id'])
                && $profile->canViewItem()
                && (new SourceRevisionManager())->softDelete($profile, (int) $sourceData['id'])) {
                $deleted++;
            }
        }
        return $deleted;
    }

    private function referencedHashes(): array
    {
        global $DB;
        $hashes = [];
        foreach ($DB->request(['SELECT' => ['source_hash'], 'DISTINCT' => true, 'FROM' => 'glpi_plugin_ticketmigration_runs']) as $run) {
            $hashes[(string) $run['source_hash']] = true;
        }
        return $hashes;
    }
}
