<?php

namespace GlpiPlugin\Ticketmigration\Tests\Unit\Source;

use GlpiPlugin\Ticketmigration\Source\SourceRetentionPolicy;
use PHPUnit\Framework\TestCase;

final class SourceRetentionPolicyTest extends TestCase
{
    public function testOnlyExpiredInactiveUnreferencedPayloadIsEligible(): void
    {
        $policy = new SourceRetentionPolicy();
        $source = ['id' => 4, 'sha256' => 'hash', 'expires_at' => '2026-08-01 00:00:00'];
        $now = '2026-08-26 00:00:00';

        self::assertTrue($policy->isCleanupCandidate($source, [], [], $now));
        self::assertFalse($policy->isCleanupCandidate($source, [4], [], $now));
        self::assertFalse($policy->isCleanupCandidate($source, [], ['hash' => true], $now));
        self::assertFalse($policy->isCleanupCandidate(array_replace($source, ['expires_at' => null]), [], [], $now));
        self::assertFalse($policy->isCleanupCandidate(array_replace($source, ['expires_at' => '2026-09-01 00:00:00']), [], [], $now));
    }
}
