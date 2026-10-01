<?php

namespace GlpiPlugin\Ticketmigration\Idempotency;

final class CanonicalRowHasher
{
    /** @param list<string|null> $values */
    public function hash(array $values): string
    {
        $canonical = array_map(static fn ($value) => str_replace(["\r\n", "\r"], "\n", (string) $value), $values);
        return hash('sha256', json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
