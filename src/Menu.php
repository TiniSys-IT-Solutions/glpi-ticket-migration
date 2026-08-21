<?php

namespace GlpiPlugin\Ticketmigration;

use CommonGLPI;

final class Menu extends CommonGLPI
{
    public static $rightname = ProfileRight::RIGHT_VIEW_PROFILES;

    public static function getMenuName(): string
    {
        return __('Ticket Migration', 'ticketmigration');
    }

    public static function getMenuContent(): array
    {
        $canViewProfiles = ProfileRight::canViewProfiles();
        $canConfigure = ProfileRight::canConfigure();
        if (!$canViewProfiles && !$canConfigure) {
            return [];
        }

        $menu = [
            'title' => self::getMenuName(),
            'page' => $canViewProfiles
                ? '/plugins/ticketmigration/front/profile.php'
                : '/plugins/ticketmigration/front/config.php',
            'icon' => 'ti ti-transfer',
            'options' => [],
        ];
        if ($canViewProfiles) {
            $menu['options']['profiles'] = [
                'title' => __('Migration profiles', 'ticketmigration'),
                'page' => '/plugins/ticketmigration/front/profile.php',
            ];
        }
        if (ProfileRight::canViewHistory()) {
            $menu['options']['runs'] = [
                'title' => __('Migration runs', 'ticketmigration'),
                'page' => '/plugins/ticketmigration/front/run.php',
            ];
        }
        if ($canConfigure) {
            $menu['options']['config'] = ['title' => __('Configuration', 'ticketmigration'), 'page' => '/plugins/ticketmigration/front/config.php'];
        }
        return $menu;
    }
}
