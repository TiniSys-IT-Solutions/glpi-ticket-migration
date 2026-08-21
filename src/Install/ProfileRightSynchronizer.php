<?php

namespace GlpiPlugin\Ticketmigration\Install;

use GlpiPlugin\Ticketmigration\ProfileRight as PluginProfileRight;

final class ProfileRightSynchronizer
{
    public function synchronize(): bool
    {
        global $DB, $GLPI_CACHE;

        $required = array_column(PluginProfileRight::rights(), 'field');
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
                    'rights' => 0,
                ]) && $ok;
            }
        }
        $GLPI_CACHE->set('all_possible_rights', []);
        return $ok;
    }
}
