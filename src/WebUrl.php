<?php

namespace GlpiPlugin\Ticketmigration;

final class WebUrl
{
    public static function plugin(bool $withRootDocument = true): string
    {
        $rootDocument = $withRootDocument
            ? rtrim((string) ($GLOBALS['CFG_GLPI']['root_doc'] ?? ''), '/')
            : '';

        return $rootDocument . '/plugins/ticketmigration';
    }

    public static function front(string $filename, bool $withRootDocument = true): string
    {
        return self::plugin($withRootDocument) . '/front/' . ltrim($filename, '/');
    }

    public static function ajax(string $filename, bool $withRootDocument = true): string
    {
        return self::plugin($withRootDocument) . '/ajax/' . ltrim($filename, '/');
    }
}
