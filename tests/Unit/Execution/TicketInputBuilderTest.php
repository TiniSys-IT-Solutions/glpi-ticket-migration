<?php

namespace GlpiPlugin\Ticketmigration\Tests\Unit\Execution;

use GlpiPlugin\Ticketmigration\Execution\TicketInputBuilder;
use GlpiPlugin\Ticketmigration\Plan\MigrationPlan;
use PHPUnit\Framework\TestCase;

final class TicketInputBuilderTest extends TestCase
{
    public function testBuildsOfficialGlpiTicketInputWithoutEnablingNotificationsOrRules(): void
    {
        $plan = new MigrationPlan(
            ticket: [
                'name' => 'Historical ticket',
                'content' => '<p>History</p>',
                'status' => '6',
                'date' => '2026-08-11 00:00:00',
                'closedate' => '2026-08-11 11:53:00',
                'entity' => ['itemtype' => 'Entity', 'id' => 18],
                'location' => ['itemtype' => 'Location', 'id' => 30],
                'category' => ['itemtype' => 'ITILCategory', 'id' => 4],
            ],
            actors: [
                'requester' => [['itemtype' => 'User', 'id' => 317]],
                'assignee' => [['itemtype' => 'User', 'id' => 31], ['itemtype' => 'User', 'id' => 32]],
                'assignee_group' => [['itemtype' => 'Group', 'id' => 9]],
            ],
            externalReference: ['external_id' => 'LEGACY-2026-33913'],
        );

        $input = (new TicketInputBuilder())->build($plan);

        self::assertTrue($input['_disablenotif']);
        self::assertTrue($input['_skip_rules']);
        self::assertTrue($input['_skip_auto_assign']);
        self::assertSame(18, $input['entities_id']);
        self::assertSame(30, $input['locations_id']);
        self::assertSame(4, $input['itilcategories_id']);
        self::assertSame([317], $input['_users_id_requester']);
        self::assertSame([31, 32], $input['_users_id_assign']);
        self::assertSame([9], $input['_groups_id_assign']);
        self::assertSame('2026-08-11 11:53:00', $input['closedate']);
        self::assertSame('LEGACY-2026-33913', $input['externalid']);
    }
}
