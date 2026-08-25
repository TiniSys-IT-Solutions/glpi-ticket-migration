<?php

namespace GlpiPlugin\Ticketmigration\Mapping;

final class UserDropdownScope
{
    /** @return array{right: string, with_no_right: int, entity?: int, entity_sons?: bool} */
    public static function forTarget(string $targetKey): array
    {
        return $targetKey === 'actor.assignee'
            ? ['right' => 'own_ticket', 'with_no_right' => 0]
            : ['right' => 'all', 'with_no_right' => 1, 'entity' => -1, 'entity_sons' => false];
    }
}
