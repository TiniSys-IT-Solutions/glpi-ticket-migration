<?php

namespace GlpiPlugin\Ticketmigration\Tests\Unit;

use GlpiPlugin\Ticketmigration\Install\RightSet;
use PHPUnit\Framework\TestCase;

final class RightSetTest extends TestCase
{
    public function testOnlyMissingRightsAreReturnedAfterPartialInstall(): void
    {
        $required = ['profiles', 'manage_profiles', 'dry_run'];
        $existing = ['profiles' => 0];

        self::assertSame(
            ['manage_profiles', 'dry_run'],
            RightSet::missing($required, $existing),
        );
    }

    public function testRepeatedSynchronizationHasNothingToInsert(): void
    {
        $required = ['profiles', 'manage_profiles'];
        $existing = ['profiles' => 0, 'manage_profiles' => 2];

        self::assertSame([], RightSet::missing($required, $existing));
    }
}
