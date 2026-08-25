<?php

namespace GlpiPlugin\Ticketmigration\Mapping;

final class ReferenceSelection
{
    /** @return array{itemtype: string, id: int}|null */
    public static function parse(string $value): ?array
    {
        if (!str_starts_with($value, 'ref:')) {
            return null;
        }
        $parts = explode(':', $value, 3);
        if (count($parts) !== 3 || $parts[1] === '' || !ctype_digit($parts[2])) {
            return null;
        }
        $id = (int) $parts[2];
        if ($id <= 0 || !ctype_alpha(str_replace(['_', '\\'], '', $parts[1]))) {
            return null;
        }
        return ['itemtype' => $parts[1], 'id' => $id];
    }
}
