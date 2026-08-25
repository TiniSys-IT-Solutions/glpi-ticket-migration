<?php

namespace GlpiPlugin\Ticketmigration\Source;

use GlpiPlugin\Ticketmigration\MigrationProfile;
use GlpiPlugin\Ticketmigration\SourceFile;

final class SourceRevisionManager
{
    public function activate(MigrationProfile $profile, int $sourceId): bool
    {
        global $DB;
        $source = new SourceFile();
        if (!$source->getFromDB($sourceId)
            || (int) $source->fields['profiles_id'] !== (int) $profile->getID()
            || $source->fields['deleted_at'] !== null) {
            return false;
        }

        $step = MigrationProfile::STEP_SOURCE_SELECTED;
        $current = new SourceFile();
        if ($current->getFromDB((int) ($profile->fields['sourcefiles_id'] ?? 0))
            && $current->fields['schema_fingerprint'] === $source->fields['schema_fingerprint']
            && countElementsInTable('glpi_plugin_ticketmigration_fieldmappings', ['profiles_id' => (int) $profile->getID()]) > 0) {
            $step = MigrationProfile::STEP_MAPPING_CONFIGURED;
        }

        return $DB->update(MigrationProfile::getTable(), [
            'sourcefiles_id' => $sourceId,
            'workflow_step' => $step,
            'is_ready' => 0,
        ], ['id' => (int) $profile->getID()]);
    }

    public function revisions(MigrationProfile $profile): array
    {
        global $DB;
        return iterator_to_array($DB->request([
            'FROM' => SourceFile::getTable(),
            'WHERE' => ['profiles_id' => (int) $profile->getID(), 'deleted_at' => null],
            'ORDER' => ['uploaded_at DESC', 'id DESC'],
        ]));
    }

    public function softDelete(MigrationProfile $profile, int $sourceId): bool
    {
        global $DB;
        if ((int) ($profile->fields['sourcefiles_id'] ?? 0) === $sourceId) {
            return false;
        }
        $source = new SourceFile();
        if (!$source->getFromDB($sourceId)
            || (int) $source->fields['profiles_id'] !== (int) $profile->getID()) {
            return false;
        }
        if (countElementsInTable('glpi_plugin_ticketmigration_runs', ['source_hash' => $source->fields['sha256']]) > 0) {
            return false;
        }
        if (!$DB->update(SourceFile::getTable(), ['deleted_at' => date('Y-m-d H:i:s')], ['id' => $sourceId])) {
            return false;
        }
        try {
            $path = $source->getProtectedPath();
            if (is_file($path)) {
                unlink($path);
            }
        } catch (\Throwable) {
            // Metadata remains deleted even when the retained file is already absent.
        }
        return true;
    }
}
