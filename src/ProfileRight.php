<?php

namespace GlpiPlugin\Ticketmigration;

use CommonGLPI;
use Session;

final class ProfileRight extends \Profile
{
    public const RIGHT_VIEW_PROFILES = 'plugin_ticketmigration_profiles';
    public const RIGHT_MANAGE_PROFILES = 'plugin_ticketmigration_manage_profiles';
    public const RIGHT_DRY_RUN = 'plugin_ticketmigration_dry_run';
    public const RIGHT_RUN = 'plugin_ticketmigration_run';
    public const RIGHT_HISTORY = 'plugin_ticketmigration_history';
    public const RIGHT_CONFIG = 'plugin_ticketmigration_config';

    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0): string
    {
        return $item instanceof \Profile && $item->getID() > 0
            ? self::createTabEntry(__('Ticket Migration', 'ticketmigration'))
            : '';
    }

    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
    {
        if ($item instanceof \Profile) {
            (new self())->showRights((int) $item->getID());
        }
        return true;
    }

    private function showRights(int $profilesId): void
    {
        $this->displayRightsChoiceMatrix(self::rights(), [
            'canedit' => Session::haveRight('profile', UPDATE),
            'title' => __('Ticket Migration', 'ticketmigration'),
        ]);
    }

    public static function rights(): array
    {
        return [
            ['itemtype' => self::class, 'label' => __('View migration profiles', 'ticketmigration'), 'field' => self::RIGHT_VIEW_PROFILES],
            ['itemtype' => self::class, 'label' => __('Manage migration profiles', 'ticketmigration'), 'field' => self::RIGHT_MANAGE_PROFILES],
            ['itemtype' => self::class, 'label' => __('Run dry runs', 'ticketmigration'), 'field' => self::RIGHT_DRY_RUN],
            ['itemtype' => self::class, 'label' => __('Run imports', 'ticketmigration'), 'field' => self::RIGHT_RUN],
            ['itemtype' => self::class, 'label' => __('View migration history', 'ticketmigration'), 'field' => self::RIGHT_HISTORY],
            ['itemtype' => self::class, 'label' => __('Manage plugin configuration', 'ticketmigration'), 'field' => self::RIGHT_CONFIG],
        ];
    }
}
