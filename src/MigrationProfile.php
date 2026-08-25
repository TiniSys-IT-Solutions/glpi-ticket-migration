<?php

namespace GlpiPlugin\Ticketmigration;

use CommonDBTM;
use Session;

final class MigrationProfile extends CommonDBTM
{
    public const STEP_PROFILE_CREATED = 'profile_created';
    public const STEP_SOURCE_SELECTED = 'source_selected';
    public const STEP_MAPPING_CONFIGURED = 'mapping_configured';
    public const STEP_VALUES_CONFIGURED = 'values_configured';
    public const STEP_DRY_RUN_VALIDATED = 'dry_run_validated';

    public static $rightname = ProfileRight::RIGHT_VIEW_PROFILES;

    public static function getTable($classname = null): string
    {
        return 'glpi_plugin_ticketmigration_profiles';
    }

    public static function getTypeName($nb = 0): string
    {
        return _n('Migration profile', 'Migration profiles', $nb, 'ticketmigration');
    }

    public static function getFormURL($full = true): string
    {
        return WebUrl::front('profile.form.php', $full);
    }

    public static function getSearchURL($full = true): string
    {
        return WebUrl::front('profile.php', $full);
    }

    public function canViewItem(): bool
    {
        if (!Session::haveAccessToEntity((int) $this->fields['entities_id'], (bool) $this->fields['is_recursive'])) {
            return false;
        }
        return !(bool) $this->fields['is_private']
            || (int) $this->fields['users_id'] === (int) Session::getLoginUserID();
    }

    public function canCreateItem(): bool
    {
        return ProfileRight::canManageProfiles(CREATE)
            && Session::haveAccessToEntity((int) ($this->input['entities_id'] ?? Session::getActiveEntity()));
    }

    public function canUpdateItem(): bool
    {
        return ProfileRight::canManageProfiles(UPDATE) && $this->canViewItem();
    }

    public function prepareInputForAdd($input): array|false
    {
        $input['users_id'] = Session::getLoginUserID();
        $input['entities_id'] ??= Session::getActiveEntity();
        $input['is_ready'] = 0;
        $input['workflow_step'] = self::STEP_PROFILE_CREATED;
        return $this->normalizeInput($input);
    }

    public function prepareInputForUpdate($input): array|false
    {
        unset($input['users_id'], $input['is_ready'], $input['sourcefiles_id'], $input['workflow_step']);
        return $this->normalizeInput($input);
    }

    private function normalizeInput(array $input): array|false
    {
        $name = trim((string) ($input['name'] ?? $this->fields['name'] ?? ''));
        if ($name === '') {
            Session::addMessageAfterRedirect(__('A migration profile name is required.', 'ticketmigration'), false, ERROR);
            return false;
        }
        $input['name'] = $name;
        if (array_key_exists('entities_id', $input) && !Session::haveAccessToEntity((int) $input['entities_id'])) {
            Session::addMessageAfterRedirect(__('You do not have permission to use the selected default entity.', 'ticketmigration'), false, ERROR);
            return false;
        }
        if (array_key_exists('source_name', $input)) {
            $input['source_name'] = trim((string) $input['source_name']);
        }
        if (array_key_exists('is_private', $input)) {
            $input['is_private'] = (int) (bool) $input['is_private'];
        }
        if (array_key_exists('is_recursive', $input)) {
            $input['is_recursive'] = (int) (bool) $input['is_recursive'];
        }
        return $input;
    }
}
