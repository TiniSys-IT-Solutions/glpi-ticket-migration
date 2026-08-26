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
use GlpiPlugin\Ticketmigration\Source\SourceRetentionService;
use GlpiPlugin\Ticketmigration\WebUrl;

$retention = new SourceRetentionService();
if (isset($_POST['cleanup_expired']) && ProfileRight::canManageProfiles(DELETE)) {
    $deleted = $retention->cleanupExpired();
    Session::addMessageAfterRedirect(sprintf(_n('%d expired CSV revision was cleaned.', '%d expired CSV revisions were cleaned.', $deleted, 'ticketmigration'), $deleted));
    Html::redirect(WebUrl::front('config.php'));
}
$storageReport = $retention->report();
$profilesCount = countElementsInTable(MigrationProfile::getTable(), ['is_archived' => 0]);
$sourcesCount = countElementsInTable(SourceFile::getTable(), ['deleted_at' => null]);
$activeSourcesCount = countElementsInTable(MigrationProfile::getTable(), ['NOT' => ['sourcefiles_id' => null]]);
$runsCount = countElementsInTable('glpi_plugin_ticketmigration_runs');
$storagePath = SourceDirectory::path();
$canManageProfiles = ProfileRight::canManageProfiles(UPDATE);
$root = WebUrl::plugin() . '/front';

Html::header(__('Ticket Migration configuration', 'ticketmigration'), $_SERVER['PHP_SELF'], 'tools', \GlpiPlugin\Ticketmigration\Menu::class);
Glpi\Application\View\TemplateRenderer::getInstance()->display(
    '@ticketmigration/config/dashboard.html.twig',
    [
        'version' => PLUGIN_TICKETMIGRATION_VERSION,
        'profiles_count' => $profilesCount,
        'sources_count' => $sourcesCount,
        'active_sources_count' => $activeSourcesCount,
        'runs_count' => $runsCount,
        'storage_path' => $storagePath,
        'storage_ready' => is_dir($storagePath) && is_writable($storagePath),
        'storage_report' => $storageReport,
        'cleanup_url' => WebUrl::front('config.php'),
        'can_manage_profiles' => $canManageProfiles,
        'can_cleanup_sources' => ProfileRight::canManageProfiles(DELETE),
        'profiles_url' => $root . '/profile.php',
        'new_profile_url' => MigrationProfile::getFormURL(),
        'runs_url' => $root . '/run.php',
    ],
);
Html::footer();
