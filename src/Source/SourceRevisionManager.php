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
        $currentId = (int) ($profile->fields['sourcefiles_id'] ?? 0);
        $currentMatches = $current->getFromDB($currentId)
            && $current->fields['schema_fingerprint'] === $source->fields['schema_fingerprint'];
        $profileMatches = (string) ($profile->fields['schema_fingerprint'] ?? '') !== ''
            && $profile->fields['schema_fingerprint'] === $source->fields['schema_fingerprint'];
        if (($currentMatches || $profileMatches)
            && countElementsInTable('glpi_plugin_ticketmigration_fieldmappings', ['profiles_id' => (int) $profile->getID()]) > 0) {
            $step = MigrationProfile::STEP_MAPPING_CONFIGURED;
        }
        $DB->beginTransaction();
        try {
            if ($currentId > 0 && $currentId !== $sourceId) {
                $DB->update(SourceFile::getTable(), ['expires_at' => date('Y-m-d H:i:s', strtotime('+30 days'))], ['id' => $currentId]);
            }
            $DB->update(SourceFile::getTable(), ['expires_at' => null], ['id' => $sourceId]);
            $updated = $DB->update(MigrationProfile::getTable(), [
            'sourcefiles_id' => $sourceId,
            'schema_fingerprint' => $source->fields['schema_fingerprint'],
            'workflow_step' => $step,
            'is_ready' => 0,
            ], ['id' => (int) $profile->getID()]);
            $DB->commit();
            return $updated;
        } catch (\Throwable $exception) {
            $DB->rollBack();
            throw $exception;
        }
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
