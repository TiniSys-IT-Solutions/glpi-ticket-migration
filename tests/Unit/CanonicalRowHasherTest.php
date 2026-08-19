<?php

namespace GlpiPlugin\Ticketmigration\Tests\Unit;

use GlpiPlugin\Ticketmigration\Idempotency\CanonicalRowHasher;
use PHPUnit\Framework\TestCase;

final class CanonicalRowHasherTest extends TestCase
{
    public function testLineEndingsAreCanonical(): void
    {
        $hasher = new CanonicalRowHasher();
        self::assertSame($hasher->hash(['a', "b\r\nc"]), $hasher->hash(['a', "b\nc"]));
    }
    public function testColumnOrderMatters(): void
    {
        $hasher = new CanonicalRowHasher();
        self::assertNotSame($hasher->hash(['a', 'b']), $hasher->hash(['b', 'a']));
    }
}
