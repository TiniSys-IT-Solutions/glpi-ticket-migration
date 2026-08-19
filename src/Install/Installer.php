<?php

namespace GlpiPlugin\Ticketmigration\Install;

final class Installer
{
    private const TABLES = [
        'glpi_plugin_ticketmigration_runitems', 'glpi_plugin_ticketmigration_externalrefs',
        'glpi_plugin_ticketmigration_runs', 'glpi_plugin_ticketmigration_valuemappings',
        'glpi_plugin_ticketmigration_fieldmappings', 'glpi_plugin_ticketmigration_profiles',
        'glpi_plugin_ticketmigration_configs',
    ];

    public function install(): bool
    {
        global $DB;
        $migration = new \Migration(PLUGIN_TICKETMIGRATION_VERSION);
        foreach (Schema::tables() as $table => $sql) {
            if (!$DB->tableExists($table)) {
                $DB->doQuery($sql);
            }
        }
        foreach (\GlpiPlugin\Ticketmigration\ProfileRight::rights() as $right) {
            \ProfileRight::addProfileRights([$right['field']]);
        }
        $migration->executeMigration();
        return true;
    }

    public function uninstall(): bool
    {
        global $DB;
        foreach (self::TABLES as $table) {
            if ($DB->tableExists($table)) {
                $DB->dropTable($table);
            }
        }
        foreach (\GlpiPlugin\Ticketmigration\ProfileRight::rights() as $right) {
            \ProfileRight::deleteProfileRights([$right['field']]);
        }
        return true;
    }
}
