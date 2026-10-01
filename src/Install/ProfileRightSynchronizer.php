<?php

namespace GlpiPlugin\Ticketmigration\Install;

use GlpiPlugin\Ticketmigration\ProfileRight as PluginProfileRight;

final class ProfileRightSynchronizer
{
    private const BOOTSTRAP_MARKER = 'profile_rights_bootstrapped_v1';

    public function synchronize(): bool
    {
        global $DB, $GLPI_CACHE;

        $required = array_column(PluginProfileRight::rights(), 'field');
        $bootstrapAdministrativeProfiles = !$this->hasBootstrapMarker();
        $ok = true;
        foreach ($DB->request([
            'SELECT' => ['id'],
            'FROM' => \Profile::getTable(),
        ]) as $profile) {
            $profileId = (int) $profile['id'];
            $existing = \ProfileRight::getProfileRights($profileId, $required);
            foreach (RightSet::missing($required, $existing) as $name) {
                $ok = $DB->insert(\ProfileRight::getTable(), [
                    'profiles_id' => $profileId,
                    'name' => $name,
                    'rights' => $bootstrapAdministrativeProfiles && $this->canConfigureGlpi($profileId)
                        ? ALLSTANDARDRIGHT
                        : 0,
                ]) && $ok;
            }

            if ($bootstrapAdministrativeProfiles && $this->canConfigureGlpi($profileId)) {
                $ok = $DB->update(\ProfileRight::getTable(), [
                    'rights' => ALLSTANDARDRIGHT,
                ], [
                    'profiles_id' => $profileId,
                    'name' => $required,
                ]) && $ok;
            }
        }
        if ($bootstrapAdministrativeProfiles && $ok) {
            $ok = $DB->insert('glpi_plugin_ticketmigration_configs', [
                'name' => self::BOOTSTRAP_MARKER,
                'value' => '1',
            ]) && $ok;
        }
        $GLPI_CACHE->set('all_possible_rights', []);
        return $ok;
    }

    private function hasBootstrapMarker(): bool
    {
        return countElementsInTable('glpi_plugin_ticketmigration_configs', [
            'name' => self::BOOTSTRAP_MARKER,
        ]) > 0;
    }

    private function canConfigureGlpi(int $profileId): bool
    {
        $rights = \ProfileRight::getProfileRights($profileId, ['config']);
        return (((int) ($rights['config'] ?? 0)) & UPDATE) === UPDATE;
    }
}
