<?php

namespace GlpiPlugin\Ticketmigration\Tests\Unit\Plan;

use GlpiPlugin\Ticketmigration\Plan\DateNormalizer;
use PHPUnit\Framework\TestCase;

final class DateNormalizerTest extends TestCase
{
    public function testNormalizesFrenchAndIsoDatesForGlpi(): void
    {
        $normalizer = new DateNormalizer();
        self::assertSame('2026-08-11 00:00:00', $normalizer->normalize('11/08/2026'));
        self::assertSame('2026-08-11 11:53:00', $normalizer->normalize('11/08/2026 11:53'));
        self::assertSame('2026-08-11 11:53:04', $normalizer->normalize('2026-08-11 11:53:04'));
        self::assertNull($normalizer->normalize('31/02/2026'));
    }
}
