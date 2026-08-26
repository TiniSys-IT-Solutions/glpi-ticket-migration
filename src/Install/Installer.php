<?php

namespace GlpiPlugin\Ticketmigration\Install;

use Glpi\Cache\CacheManager;

final class Installer
{
    private const TABLES = [
        'glpi_plugin_ticketmigration_runitems', 'glpi_plugin_ticketmigration_externalrefs',
        'glpi_plugin_ticketmigration_runs', 'glpi_plugin_ticketmigration_valuemappings',
        'glpi_plugin_ticketmigration_locationentitymappings',
        'glpi_plugin_ticketmigration_fieldmappings', 'glpi_plugin_ticketmigration_profiles',
        'glpi_plugin_ticketmigration_sourcefiles',
        'glpi_plugin_ticketmigration_configs',
    ];

    public function install(): bool
    {
        global $DB;
        $migration = new \Migration(PLUGIN_TICKETMIGRATION_VERSION);
        SourceDirectory::ensureExists();
        foreach (Schema::tables() as $table => $sql) {
            if (!$DB->tableExists($table)) {
                $DB->doQuery($sql);
            }
        }
        $profilesTable = \GlpiPlugin\Ticketmigration\MigrationProfile::getTable();
        $sourcesTable = \GlpiPlugin\Ticketmigration\SourceFile::getTable();
        $runItemsTable = 'glpi_plugin_ticketmigration_runitems';
        if (!$DB->fieldExists($sourcesTable, 'csv_config')) {
            $migration->addField($sourcesTable, 'csv_config', 'JSON DEFAULT NULL', ['after' => 'schema_fingerprint']);
        }
        if (!$DB->fieldExists($profilesTable, 'sourcefiles_id')) {
            $migration->addField($profilesTable, 'sourcefiles_id', 'BIGINT UNSIGNED DEFAULT NULL', ['after' => 'is_ready']);
            $migration->addKey($profilesTable, 'sourcefiles_id', 'active_source');
        }
        if (!$DB->fieldExists($profilesTable, 'is_archived')) {
            $migration->addField($profilesTable, 'is_archived', 'bool', ['value' => 0, 'after' => 'is_ready']);
            $migration->addField($profilesTable, 'archived_at', 'timestamp', ['after' => 'is_archived']);
            $migration->addKey($profilesTable, ['is_archived', 'archived_at'], 'archive');
        }
        if (!$DB->fieldExists($profilesTable, 'schema_fingerprint')) {
            $migration->addField($profilesTable, 'schema_fingerprint', 'CHAR(64) DEFAULT NULL', ['after' => 'sourcefiles_id']);
        }
        if (!$DB->fieldExists($profilesTable, 'workflow_step')) {
            $migration->addField(
                $profilesTable,
                'workflow_step',
                'string',
                ['value' => 'profile_created', 'after' => 'sourcefiles_id'],
            );
        }
        if (!$DB->fieldExists($runItemsTable, 'information')) {
            $migration->addField($runItemsTable, 'information', 'JSON DEFAULT NULL', ['after' => 'warnings']);
        }
        if (!$DB->fieldExists($runItemsTable, 'validations')) {
            $migration->addField($runItemsTable, 'validations', 'JSON DEFAULT NULL', ['after' => 'information']);
        }
        if (!(new ProfileRightSynchronizer())->synchronize()) {
            return false;
        }
        $migration->executeMigration();

        $DB->doQuery("UPDATE `$sourcesTable` AS sources
            INNER JOIN `$profilesTable` AS profiles ON profiles.id = sources.profiles_id
            SET sources.csv_config = profiles.csv_config
            WHERE sources.csv_config IS NULL");
        $DB->doQuery("UPDATE `$profilesTable` AS profiles
            INNER JOIN `$sourcesTable` AS sources ON sources.id = profiles.sourcefiles_id
            SET profiles.schema_fingerprint = sources.schema_fingerprint
            WHERE profiles.schema_fingerprint IS NULL");

        // Upgrade existing profiles without losing their upload history. The
        // most recent non-deleted revision becomes the explicit active source.
        foreach ($DB->request(['FROM' => $profilesTable, 'WHERE' => ['sourcefiles_id' => null]]) as $profile) {
            $source = $DB->request([
                'SELECT' => ['id'],
                'FROM' => $sourcesTable,
                'WHERE' => ['profiles_id' => (int) $profile['id'], 'deleted_at' => null],
                'ORDER' => ['uploaded_at DESC', 'id DESC'],
                'LIMIT' => 1,
            ])->current();
            if ($source !== false) {
                $DB->update($profilesTable, [
                    'sourcefiles_id' => (int) $source['id'],
                    'workflow_step' => 'source_selected',
                ], ['id' => (int) $profile['id']]);
            }
        }

        // GLPI disables Twig auto-reload in production. Plugin upgrades do not
        // clear compiled templates automatically, so refreshed plugin views
        // would otherwise remain invisible until a manual cache clear.
        if (!(new CacheManager())->resetAllCaches()) {
            trigger_error('Ticket Migration installed, but GLPI caches could not be fully cleared.', E_USER_WARNING);
        }
        unset($_SESSION['glpimenu']);

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
