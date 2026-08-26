<?php

use Glpi\Plugin\Hooks;
use GlpiPlugin\Ticketmigration\Menu;
use GlpiPlugin\Ticketmigration\MigrationProfile;
use GlpiPlugin\Ticketmigration\ProfileRight;
use GlpiPlugin\Ticketmigration\SourceFile;

define('PLUGIN_TICKETMIGRATION_VERSION', '0.0.41');
define('PLUGIN_TICKETMIGRATION_MIN_GLPI', '11.0.0');
define('PLUGIN_TICKETMIGRATION_MAX_GLPI', '11.1.0');

function plugin_init_ticketmigration(): void
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS[Hooks::CSRF_COMPLIANT]['ticketmigration'] = true;
    Plugin::registerClass(ProfileRight::class, ['addtabon' => ['Profile']]);
    Plugin::registerClass(MigrationProfile::class);
    Plugin::registerClass(SourceFile::class);

    // Always register the menu provider. It performs the authorization check
    // when GLPI builds the menu, including the administrator recovery path.
    $PLUGIN_HOOKS['menu_toadd']['ticketmigration'] = ['tools' => Menu::class];

    // GLPI decides whether to display the configuration wrench from this hook.
    // The target page still performs its own authorization check.
    $PLUGIN_HOOKS['config_page']['ticketmigration'] = 'front/config.php';
}

function plugin_version_ticketmigration(): array
{
    return [
        'name' => __('Ticket Migration', 'ticketmigration'),
        'version' => PLUGIN_TICKETMIGRATION_VERSION,
        'author' => 'DooSys',
        'license' => 'GPL-3.0-or-later',
        'homepage' => 'https://github.com/TiniSys-IT-Solutions/glpi-ticket-migration',
        'requirements' => [
            'glpi' => ['min' => PLUGIN_TICKETMIGRATION_MIN_GLPI, 'max' => PLUGIN_TICKETMIGRATION_MAX_GLPI],
            'php' => ['min' => '8.2'],
        ],
    ];
}

function plugin_ticketmigration_check_prerequisites(): bool
{
    return version_compare(GLPI_VERSION, PLUGIN_TICKETMIGRATION_MIN_GLPI, '>=')
        && version_compare(GLPI_VERSION, PLUGIN_TICKETMIGRATION_MAX_GLPI, '<')
        && version_compare(PHP_VERSION, '8.2.0', '>=');
}

function plugin_ticketmigration_check_config(bool $verbose = false): bool
{
    return true;
}
