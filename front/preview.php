<?php

if (!defined('GLPI_ROOT')) {
    require dirname(__DIR__, 3) . '/inc/includes.php';
}

use GlpiPlugin\Ticketmigration\Menu;
use GlpiPlugin\Ticketmigration\ProfileRight;
use GlpiPlugin\Ticketmigration\Source\CsvConfiguration;
use GlpiPlugin\Ticketmigration\Source\PreviewService;
use GlpiPlugin\Ticketmigration\SourceFile;

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

Html::header(__('CSV preview', 'ticketmigration'), $_SERVER['PHP_SELF'], 'tools', Menu::class);
Glpi\Application\View\TemplateRenderer::getInstance()->display(
    '@ticketmigration/source/preview.html.twig',
    [
        'source' => $source->fields,
        'columns' => $preview->columns,
        'rows' => $preview->rows,
        'fingerprint' => $preview->schemaFingerprint,
    ],
);
Html::footer();
