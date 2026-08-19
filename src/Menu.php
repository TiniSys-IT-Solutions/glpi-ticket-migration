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
        $menu = [
            'title' => self::getMenuName(),
            'page' => '/plugins/ticketmigration/front/profile.php',
            'icon' => 'ti ti-transfer',
            'options' => [],
        ];
        $menu['options']['profiles'] = ['title' => __('Migration profiles', 'ticketmigration'), 'page' => $menu['page']];
        $menu['options']['runs'] = ['title' => __('Migration runs', 'ticketmigration'), 'page' => '/plugins/ticketmigration/front/run.php'];
        if (\Session::haveRight(ProfileRight::RIGHT_CONFIG, READ)) {
            $menu['options']['config'] = ['title' => __('Configuration', 'ticketmigration'), 'page' => '/plugins/ticketmigration/front/config.php'];
        }
        return $menu;
    }
}
