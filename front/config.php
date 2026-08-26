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
$tab = (string) ($_GET['tab'] ?? $_POST['tab'] ?? 'dashboard');
$tab = in_array($tab, ['dashboard', 'diagnostic'], true) ? $tab : 'dashboard';
if ($tab === 'diagnostic' && !ProfileRight::canConfigure()) { Html::displayErrorAndDie(__('You do not have permission to perform this action.')); }
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
$tabs = [
    'current' => $tab, 'dashboard_url' => WebUrl::front('config.php'),
    'profiles_url' => WebUrl::front('profile.php'), 'runs_url' => WebUrl::front('run.php'),
    'diagnostic_url' => WebUrl::front('config.php') . '?tab=diagnostic',
    'can_dashboard' => ProfileRight::canConfigure(), 'can_profiles' => ProfileRight::canViewProfiles(), 'can_runs' => ProfileRight::canViewHistory(), 'can_diagnostic' => ProfileRight::canConfigure(),
];

Html::header(__('Ticket Migration configuration', 'ticketmigration'), $_SERVER['PHP_SELF'], 'tools', \GlpiPlugin\Ticketmigration\Menu::class);
$template = '@ticketmigration/config/dashboard.html.twig';
$view = [
        'tabs' => $tabs,
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
];
if ($tab === 'diagnostic') {
    global $DB;
    $missingTables = []; $missingFields = [];
    foreach (\GlpiPlugin\Ticketmigration\Install\Schema::tables() as $table => $_sql) { if (!$DB->tableExists($table)) { $missingTables[] = $table; } }
    $requiredFields = ['glpi_plugin_ticketmigration_profiles' => ['sourcefiles_id', 'workflow_step', 'options'], 'glpi_plugin_ticketmigration_runs' => ['sourcefiles_id', 'configuration_snapshot', 'backup_confirmed_at', 'current_offset'], 'glpi_plugin_ticketmigration_runitems' => ['status', 'tickets_id', 'information', 'validations']];
    foreach ($requiredFields as $table => $fields) { if (!$DB->tableExists($table)) { continue; } foreach ($fields as $field) { if (!$DB->fieldExists($table, $field)) { $missingFields[] = $table . '.' . $field; } } }
    $missingPayloads = 0;
    foreach ($DB->request(['FROM' => SourceFile::getTable(), 'WHERE' => ['deleted_at' => null]]) as $sourceRow) {
        $name = (string) $sourceRow['internal_filename'];
        if (!preg_match('/^[a-f0-9]{64}\.csv$/', $name) || !is_file($storagePath . DIRECTORY_SEPARATOR . $name)) { $missingPayloads++; }
    }
    $view += ['glpi_version' => GLPI_VERSION, 'php_version' => PHP_VERSION, 'missing_tables' => $missingTables, 'missing_fields' => $missingFields, 'missing_payloads' => $missingPayloads,
        'running_runs' => countElementsInTable('glpi_plugin_ticketmigration_runs', ['status' => 'running']), 'paused_runs' => countElementsInTable('glpi_plugin_ticketmigration_runs', ['status' => 'paused']),
        'issue_runs' => countElementsInTable('glpi_plugin_ticketmigration_runs', ['status' => 'completed_with_issues']),
        'php_limits' => ['memory_limit' => ini_get('memory_limit'), 'max_execution_time' => ini_get('max_execution_time'), 'upload_max_filesize' => ini_get('upload_max_filesize'), 'post_max_size' => ini_get('post_max_size')],
    ];
    $template = '@ticketmigration/config/diagnostic.html.twig';
}
Glpi\Application\View\TemplateRenderer::getInstance()->display(
    $template,
    $view,
);
Html::footer();
