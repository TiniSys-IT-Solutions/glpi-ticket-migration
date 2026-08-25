<?php

if (!defined('GLPI_ROOT')) {
    require dirname(__DIR__, 3) . '/inc/includes.php';
}

use GlpiPlugin\Ticketmigration\Mapping\FieldMappingRepository;
use GlpiPlugin\Ticketmigration\Mapping\TargetRegistry;
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
$profileId = (int) ($_REQUEST['profiles_id'] ?? 0);
$profile = new MigrationProfile();
if (!$profile->getFromDB($profileId) || !$profile->canViewItem()) {
    Html::displayErrorAndDie(__('You do not have permission to view this migration profile.', 'ticketmigration'));
}
$source = new SourceFile();
if (!$source->getFromDB((int) ($profile->fields['sourcefiles_id'] ?? 0)) || !$source->canViewItem()) {
    Session::addMessageAfterRedirect(__('Select a CSV source before configuring mappings.', 'ticketmigration'), false, ERROR);
    Html::redirect(WebUrl::front('source.form.php') . '?profiles_id=' . $profileId);
}
$config = json_decode((string) ($source->fields['csv_config'] ?? ''), true) ?: [];
$configuration = new CsvConfiguration(
    delimiter: (string) ($config['delimiter'] ?? ';'),
    hasHeader: (bool) ($config['has_header'] ?? true),
    encoding: (string) ($config['encoding'] ?? 'UTF-8'),
);
$preview = (new PreviewService())->preview($source->getProtectedPath(), $configuration, 1);
$repository = new FieldMappingRepository();
if (isset($_POST['save_mapping'])) {
    if (!$profile->canUpdateItem()) {
        Html::displayErrorAndDie(__('You do not have permission to perform this action.'));
    }
    try {
        $repository->replace($profileId, $preview->columns, (array) ($_POST['target'] ?? []));
        $missing = $repository->missingRequiredTargets($profileId);
        global $DB;
        $DB->update(MigrationProfile::getTable(), [
            'workflow_step' => $missing === []
                ? MigrationProfile::STEP_MAPPING_CONFIGURED
                : MigrationProfile::STEP_SOURCE_SELECTED,
            'is_ready' => 0,
        ], ['id' => $profileId]);
        Session::addMessageAfterRedirect(
            $missing === []
                ? __('Mapping saved. The profile is ready for the future dry-run step.', 'ticketmigration')
                : __('Mapping saved, but required targets are still missing.', 'ticketmigration'),
            false,
            $missing === [] ? INFO : WARNING,
        );
        Html::redirect(WebUrl::front('mapping.form.php') . '?profiles_id=' . $profileId);
    } catch (Throwable $exception) {
        Session::addMessageAfterRedirect($exception->getMessage(), false, ERROR);
    }
}
$mappings = $repository->forProfile($profileId);
$targets = [];
foreach (TargetRegistry::definitions() as $key => $definition) {
    $targets[$definition['group']][$key] = $definition['label'];
}
Html::header(__('CSV field mapping', 'ticketmigration'), $_SERVER['PHP_SELF'], 'tools', Menu::class);
Glpi\Application\View\TemplateRenderer::getInstance()->display('@ticketmigration/mapping/form.html.twig', [
    'profile' => $profile->fields,
    'source' => $source->fields,
    'columns' => $preview->columns,
    'sample_row' => $preview->rows[0] ?? null,
    'mappings' => $mappings,
    'target_groups' => $targets,
    'required_targets' => TargetRegistry::requiredKeys(),
    'can_manage' => $profile->canUpdateItem(),
    'form_action' => WebUrl::front('mapping.form.php'),
    'profile_url' => MigrationProfile::getFormURLWithID($profileId),
    'sources_url' => WebUrl::front('source.php') . '?profiles_id=' . $profileId,
]);
Html::footer();
