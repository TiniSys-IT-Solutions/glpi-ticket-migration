<?php

namespace GlpiPlugin\Ticketmigration\Install;

final class SourceDirectory
{
    public static function path(): string
    {
        return rtrim(GLPI_PLUGIN_DOC_DIR, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . 'ticketmigration' . DIRECTORY_SEPARATOR . 'sources';
    }

    public static function ensureExists(): void
    {
        $path = self::path();
        if (!is_dir($path) && !mkdir($path, 0750, true) && !is_dir($path)) {
            throw new \RuntimeException(sprintf('Unable to create Ticket Migration source directory: %s', $path));
        }
        if (!is_writable($path)) {
            throw new \RuntimeException(sprintf('Ticket Migration source directory is not writable: %s', $path));
        }
    }
}
