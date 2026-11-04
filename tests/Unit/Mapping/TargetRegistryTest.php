<?php

namespace GlpiPlugin\Ticketmigration\Tests\Unit\Mapping;

use GlpiPlugin\Ticketmigration\Mapping\TargetRegistry;
use PHPUnit\Framework\TestCase;

final class TargetRegistryTest extends TestCase
{
    public function testRequiredTargetsAreStableAndRegistered(): void
    {
        self::assertSame(['ticket.external_id', 'ticket.name'], TargetRegistry::requiredKeys());
        foreach (TargetRegistry::requiredKeys() as $key) {
            self::assertTrue(TargetRegistry::has($key));
        }
    }

    public function testRejectsUnknownTarget(): void
    {
        self::assertFalse(TargetRegistry::has('ticket.vendor_specific_field'));
    }
}
