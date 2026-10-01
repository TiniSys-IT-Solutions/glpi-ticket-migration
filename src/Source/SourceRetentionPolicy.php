<?php

namespace GlpiPlugin\Ticketmigration\Source;

final class SourceRetentionPolicy
{
    public function isCleanupCandidate(array $source, array $activeIds, array $referencedHashes, string $now): bool
    {
        $expiresAt = (string) ($source['expires_at'] ?? '');
        return !in_array((int) $source['id'], $activeIds, true)
            && $expiresAt !== ''
            && $expiresAt <= $now
            && !isset($referencedHashes[(string) $source['sha256']]);
    }
}
