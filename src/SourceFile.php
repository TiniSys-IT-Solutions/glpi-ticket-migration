<?php

namespace GlpiPlugin\Ticketmigration;

use CommonDBTM;

final class SourceFile extends CommonDBTM
{
    public static $rightname = ProfileRight::RIGHT_MANAGE_PROFILES;

    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_ticketmigration_sourcefiles';
    }

    public static function getTypeName($nb = 0): string
    {
        return _n('Migration source file', 'Migration source files', $nb, 'ticketmigration');
    }

    public function prepareInputForAdd($input): array|false
    {
        foreach (['users_id', 'source_filename', 'internal_filename', 'sha256', 'filesize', 'mime_type', 'uploaded_at'] as $field) {
            if (!isset($input[$field]) || $input[$field] === '') {
                return false;
            }
        }
        return $input;
    }

    public function canViewItem(): bool
    {
        $profile = new MigrationProfile();
        return $profile->getFromDB((int) ($this->fields['profiles_id'] ?? 0))
            && $profile->canViewItem();
    }

    public function getProtectedPath(): string
    {
        $filename = (string) ($this->fields['internal_filename'] ?? '');
        if (!preg_match('/^[a-f0-9]{64}\.csv$/', $filename)) {
            throw new \RuntimeException('Stored source filename is invalid.');
        }
        $directory = Install\SourceDirectory::path();
        $base = realpath($directory);
        $path = realpath($directory . DIRECTORY_SEPARATOR . $filename);
        if ($base === false || $path === false || !str_starts_with($path, $base . DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('Stored source file cannot be located safely.');
        }
        return $path;
    }
}
