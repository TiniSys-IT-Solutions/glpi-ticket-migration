<?php

if (!defined('GLPI_ROOT')) {
    require dirname(__DIR__, 3) . '/inc/includes.php';
}

if (!\GlpiPlugin\Ticketmigration\ProfileRight::canConfigure()) {
    Html::displayErrorAndDie(__('You do not have permission to perform this action.'));
}

use GlpiPlugin\Ticketmigration\Install\SourceDirectory;
use GlpiPlugin\Ticketmigration\MigrationProfile;
use GlpiPlugin\Ticketmigration\ProfileRight;
use GlpiPlugin\Ticketmigration\SourceFile;

global $CFG_GLPI;

$profilesCount = countElementsInTable(MigrationProfile::getTable());
$sourcesCount = countElementsInTable(SourceFile::getTable(), ['deleted_at' => null]);
$runsCount = countElementsInTable('glpi_plugin_ticketmigration_runs');
$storagePath = SourceDirectory::path();
$canManageProfiles = ProfileRight::canManageProfiles(UPDATE);
$root = $CFG_GLPI['root_doc'] . '/plugins/ticketmigration/front';

Html::header(__('Ticket Migration configuration', 'ticketmigration'), $_SERVER['PHP_SELF'], 'tools', \GlpiPlugin\Ticketmigration\Menu::class);
Glpi\Application\View\TemplateRenderer::getInstance()->display(
    '@ticketmigration/config/dashboard.html.twig',
    [
        'version' => PLUGIN_TICKETMIGRATION_VERSION,
        'profiles_count' => $profilesCount,
        'sources_count' => $sourcesCount,
        'runs_count' => $runsCount,
        'storage_path' => $storagePath,
        'storage_ready' => is_dir($storagePath) && is_writable($storagePath),
        'can_manage_profiles' => $canManageProfiles,
        'profiles_url' => $root . '/profile.php',
        'new_profile_url' => MigrationProfile::getFormURL(),
        'runs_url' => $root . '/run.php',
    ],
);
Html::footer();
