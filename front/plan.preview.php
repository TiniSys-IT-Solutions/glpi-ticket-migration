<?php

if (!defined('GLPI_ROOT')) {
    require dirname(__DIR__, 3) . '/inc/includes.php';
}

use GlpiPlugin\Ticketmigration\Mapping\FieldMappingRepository;
use GlpiPlugin\Ticketmigration\Mapping\ValueMappingRepository;
use GlpiPlugin\Ticketmigration\Menu;
use GlpiPlugin\Ticketmigration\MigrationProfile;
use GlpiPlugin\Ticketmigration\Plan\MigrationPlanBuilder;
use GlpiPlugin\Ticketmigration\ProfileRight;
use GlpiPlugin\Ticketmigration\Source\CsvConfiguration;
use GlpiPlugin\Ticketmigration\Source\CsvReader;
use GlpiPlugin\Ticketmigration\SourceFile;
use GlpiPlugin\Ticketmigration\WebUrl;

if (!ProfileRight::canViewProfiles()) {
    Html::displayErrorAndDie(__('You do not have permission to perform this action.'));
}
$profileId = (int) ($_GET['profiles_id'] ?? 0);
$profile = new MigrationProfile();
if (!$profile->getFromDB($profileId) || !$profile->canViewItem()) {
    Html::displayErrorAndDie(__('You do not have permission to view this migration profile.', 'ticketmigration'));
}
if (($profile->fields['workflow_step'] ?? '') !== MigrationProfile::STEP_VALUES_CONFIGURED) {
    Session::addMessageAfterRedirect(__('Complete value correspondence before previewing a migration plan.', 'ticketmigration'), false, ERROR);
    Html::redirect(WebUrl::front('value.form.php') . '?profiles_id=' . $profileId);
}
$source = new SourceFile();
if (!$source->getFromDB((int) $profile->fields['sourcefiles_id']) || !$source->canViewItem()) {
    Html::displayErrorAndDie(__('The active CSV source is unavailable.', 'ticketmigration'));
}
$config = json_decode((string) $source->fields['csv_config'], true) ?: [];
$reader = new CsvReader($source->getProtectedPath(), new CsvConfiguration(
    delimiter: (string) ($config['delimiter'] ?? ';'), hasHeader: (bool) ($config['has_header'] ?? true), encoding: (string) ($config['encoding'] ?? 'UTF-8'),
));
$rows = $reader->preview(1);
$row = $rows[0] ?? null;
$profileOptions = json_decode((string) ($profile->fields['options'] ?? ''), true) ?: [];
$plan = $row ? (new MigrationPlanBuilder())->build(
    $row,
    (new FieldMappingRepository())->forProfile($profileId),
    (new ValueMappingRepository())->forProfile($profileId),
    $reader->columns(),
    (array) ($profileOptions['description_consolidation'] ?? []),
) : null;
Html::header(__('First-row migration plan', 'ticketmigration'), $_SERVER['PHP_SELF'], 'tools', Menu::class);
Glpi\Application\View\TemplateRenderer::getInstance()->display('@ticketmigration/plan/preview.html.twig', [
    'profile' => $profile->fields, 'source' => $source->fields, 'row' => $row, 'plan' => $plan,
    'plan_json' => $plan ? json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : '',
    'values_url' => WebUrl::front('value.form.php') . '?profiles_id=' . $profileId,
]);
Html::footer();
