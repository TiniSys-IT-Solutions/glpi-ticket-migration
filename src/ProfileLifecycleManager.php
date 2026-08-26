<?php

namespace GlpiPlugin\Ticketmigration;

final class ProfileLifecycleManager
{
    public function archive(MigrationProfile $profile, bool $archived): bool
    {
        global $DB;
        if (!ProfileRight::canManageProfiles(UPDATE) || !$profile->canViewItem()) {
            return false;
        }
        return $DB->update(MigrationProfile::getTable(), [
            'is_archived' => (int) $archived,
            'archived_at' => $archived ? date('Y-m-d H:i:s') : null,
        ], ['id' => (int) $profile->getID()]);
    }

    public function cloneConfiguration(MigrationProfile $source): int
    {
        global $DB;
        if (!$source->canViewItem() || !ProfileRight::canManageProfiles(CREATE)) {
            return 0;
        }
        $clone = new MigrationProfile();
        $cloneId = $clone->add([
            'name' => sprintf(__('Copy of %s', 'ticketmigration'), (string) $source->fields['name']),
            'comment' => $source->fields['comment'] ?? null,
            'source_name' => $source->fields['source_name'] ?? '',
            'entities_id' => (int) $source->fields['entities_id'],
            'is_recursive' => (int) $source->fields['is_recursive'],
            'is_private' => (int) $source->fields['is_private'],
            'csv_config' => $source->fields['csv_config'] ?? null,
            'options' => $source->fields['options'] ?? null,
        ]);
        if (!$cloneId) {
            return 0;
        }

        $DB->beginTransaction();
        try {
            $DB->update(MigrationProfile::getTable(), [
                'schema_fingerprint' => $source->fields['schema_fingerprint'] ?? null,
            ], ['id' => $cloneId]);
            foreach (['glpi_plugin_ticketmigration_fieldmappings', 'glpi_plugin_ticketmigration_valuemappings'] as $table) {
                foreach ($DB->request(['FROM' => $table, 'WHERE' => ['profiles_id' => (int) $source->getID()]]) as $row) {
                    unset($row['id']);
                    $row['profiles_id'] = $cloneId;
                    $DB->insert($table, $row);
                }
            }
            $DB->commit();
        } catch (\Throwable $exception) {
            $DB->rollBack();
            $DB->delete(MigrationProfile::getTable(), ['id' => $cloneId]);
            throw $exception;
        }
        return $cloneId;
    }

    public function deleteWithoutHistory(MigrationProfile $profile): bool
    {
        global $DB;
        $profileId = (int) $profile->getID();
        if (!ProfileRight::canManageProfiles(DELETE) || !$profile->canViewItem()
            || countElementsInTable('glpi_plugin_ticketmigration_runs', ['profiles_id' => $profileId]) > 0
            || countElementsInTable('glpi_plugin_ticketmigration_externalrefs', ['profiles_id' => $profileId]) > 0) {
            return false;
        }

        $paths = [];
        foreach ($DB->request(['SELECT' => ['id'], 'FROM' => SourceFile::getTable(), 'WHERE' => ['profiles_id' => $profileId]]) as $row) {
            $source = new SourceFile();
            if ($source->getFromDB((int) $row['id'])) {
                try {
                    $paths[] = $source->getProtectedPath();
                } catch (\Throwable) {
                    // Missing source payload does not prevent metadata cleanup.
                }
            }
        }

        $DB->beginTransaction();
        try {
            $DB->delete('glpi_plugin_ticketmigration_valuemappings', ['profiles_id' => $profileId]);
            $DB->delete('glpi_plugin_ticketmigration_fieldmappings', ['profiles_id' => $profileId]);
            $DB->delete(SourceFile::getTable(), ['profiles_id' => $profileId]);
            $deleted = $DB->delete(MigrationProfile::getTable(), ['id' => $profileId]);
            $DB->commit();
        } catch (\Throwable $exception) {
            $DB->rollBack();
            throw $exception;
        }
        if (!$deleted) {
            return false;
        }
        foreach (array_unique($paths) as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
        return true;
    }
}
