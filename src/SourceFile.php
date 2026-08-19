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
}
