<?php

if (!defined('GLPI_ROOT')) {
    require dirname(__DIR__, 3) . '/inc/includes.php';
}

use GlpiPlugin\Ticketmigration\Menu;
use GlpiPlugin\Ticketmigration\MigrationProfile;
use GlpiPlugin\Ticketmigration\ProfileRight;
use GlpiPlugin\Ticketmigration\Source\CsvConfiguration;
use GlpiPlugin\Ticketmigration\Source\PreviewService;
use GlpiPlugin\Ticketmigration\SourceFile;
use GlpiPlugin\Ticketmigration\WebUrl;

if (!ProfileRight::canViewProfiles()) {
    Html::displayErrorAndDie(__('You do not have permission to perform this action.'));
}

$source = new SourceFile();
$id = (int) ($_GET['id'] ?? 0);
if (!$source->getFromDB($id) || !$source->canViewItem()) {
    Html::displayErrorAndDie(__('You do not have permission to view this CSV source.', 'ticketmigration'));
}
$config = json_decode((string) ($source->fields['csv_config'] ?? ''), true);
if (!is_array($config)) {
    global $DB;
    $profile = $DB->request([
        'SELECT' => ['csv_config'],
        'FROM' => 'glpi_plugin_ticketmigration_profiles',
        'WHERE' => ['id' => (int) $source->fields['profiles_id']],
        'LIMIT' => 1,
    ])->current();
    $config = json_decode((string) ($profile['csv_config'] ?? ''), true) ?: [];
}
$configuration = new CsvConfiguration(
    delimiter: (string) ($config['delimiter'] ?? ';'),
    hasHeader: (bool) ($config['has_header'] ?? true),
    encoding: (string) ($config['encoding'] ?? 'UTF-8'),
);
$preview = (new PreviewService())->preview($source->getProtectedPath(), $configuration, 10);
$profileId = (int) $source->fields['profiles_id'];
$migrationProfile = new MigrationProfile();
if (!$migrationProfile->getFromDB($profileId) || !$migrationProfile->canViewItem()) {
    Html::displayErrorAndDie(__('You do not have permission to view this migration profile.', 'ticketmigration'));
}

Html::header(__('CSV preview', 'ticketmigration'), $_SERVER['PHP_SELF'], 'tools', Menu::class);
Glpi\Application\View\TemplateRenderer::getInstance()->display(
    '@ticketmigration/source/preview.html.twig',
    [
        'source' => $source->fields,
        'columns' => $preview->columns,
        'rows' => $preview->rows,
        'fingerprint' => $preview->schemaFingerprint,
        'is_truncated' => $preview->isTruncated,
        'preview_limit' => 10,
        'profile_url' => MigrationProfile::getFormURLWithID($profileId),
        'upload_url' => WebUrl::front('source.form.php') . '?profiles_id=' . $profileId,
        'can_manage' => ProfileRight::canManageProfiles(UPDATE),
        'is_active' => (int) ($migrationProfile->fields['sourcefiles_id'] ?? 0) === $id,
        'mapping_url' => WebUrl::front('mapping.form.php') . '?profiles_id=' . $profileId,
        'sources_url' => WebUrl::front('source.php') . '?profiles_id=' . $profileId,
        'plugin_version' => PLUGIN_TICKETMIGRATION_VERSION,
    ],
);
Html::footer();
