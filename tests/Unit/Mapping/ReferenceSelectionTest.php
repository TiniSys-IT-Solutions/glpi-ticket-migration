<?php

namespace GlpiPlugin\Ticketmigration\Tests\Unit\Mapping;

use GlpiPlugin\Ticketmigration\Mapping\ReferenceSelection;
use PHPUnit\Framework\TestCase;

final class ReferenceSelectionTest extends TestCase
{
    public function testParsesUserReferencesWithoutAmbiguousCharacterClasses(): void
    {
        self::assertSame(['itemtype' => 'User', 'id' => 123], ReferenceSelection::parse('ref:User:123'));
        self::assertSame(['itemtype' => 'Glpi\\Asset\\Asset', 'id' => 42], ReferenceSelection::parse('ref:Glpi\\Asset\\Asset:42'));
        self::assertNull(ReferenceSelection::parse('ref:User:not-an-id'));
        self::assertNull(ReferenceSelection::parse('value:123'));
    }
}
