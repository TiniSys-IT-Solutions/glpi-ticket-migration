<?php

namespace GlpiPlugin\Ticketmigration\Tests\Unit\Mapping;

use GlpiPlugin\Ticketmigration\Mapping\UserDropdownScope;
use PHPUnit\Framework\TestCase;

final class UserDropdownScopeTest extends TestCase
{
    public function testRequesterIncludesAllVisibleUsersAndAssigneeUsesTechnicianRight(): void
    {
        self::assertSame(
            ['right' => 'all', 'with_no_right' => 1],
            UserDropdownScope::forTarget('actor.requester'),
        );
        self::assertSame(
            ['right' => 'own_ticket', 'with_no_right' => 0],
            UserDropdownScope::forTarget('actor.assignee'),
        );
    }
}
