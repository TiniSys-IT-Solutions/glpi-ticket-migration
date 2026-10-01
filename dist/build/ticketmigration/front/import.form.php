<?php

if (!defined('GLPI_ROOT')) { require dirname(__DIR__, 3) . '/inc/includes.php'; }

use GlpiPlugin\Ticketmigration\Execution\RunRepository;
use GlpiPlugin\Ticketmigration\Menu;
use GlpiPlugin\Ticketmigration\MigrationProfile;
use GlpiPlugin\Ticketmigration\ProfileRight;
use GlpiPlugin\Ticketmigration\Source\CsvConfiguration;
use GlpiPlugin\Ticketmigration\Source\CsvReader;
use GlpiPlugin\Ticketmigration\SourceFile;
use GlpiPlugin\Ticketmigration\WebUrl;

if (!ProfileRight::canRunImports() || !Ticket::canCreate()) { Html::displayErrorAndDie(__('You do not have permission to import tickets.', 'ticketmigration')); }
$profileId = (int) ($_REQUEST['profiles_id'] ?? 0);
$profile = new MigrationProfile();
if (!$profile->getFromDB($profileId) || !$profile->canViewItem()) { Html::displayErrorAndDie(__('You do not have permission to view this migration profile.', 'ticketmigration')); }
if (($profile->fields['workflow_step'] ?? '') !== MigrationProfile::STEP_VALUES_CONFIGURED) { Html::displayErrorAndDie(__('Complete value correspondence before preparing the final import.', 'ticketmigration')); }
$source = new SourceFile();
if (!$source->getFromDB((int) $profile->fields['sourcefiles_id']) || !$source->canViewItem()) { Html::displayErrorAndDie(__('The active CSV source is unavailable.', 'ticketmigration')); }
$config = json_decode((string) $source->fields['csv_config'], true) ?: [];
$reader = new CsvReader($source->getProtectedPath(), new CsvConfiguration(delimiter: (string) ($config['delimiter'] ?? ';'), hasHeader: (bool) ($config['has_header'] ?? true), encoding: (string) ($config['encoding'] ?? 'UTF-8')));
$totalRows = $reader->countRows();
if (isset($_POST['start_final_import'])) {
    if (!isset($_POST['backup_confirmed'])) { Session::addMessageAfterRedirect(__('You must confirm your backup responsibility before starting the final import.', 'ticketmigration'), false, ERROR); Html::redirect(WebUrl::front('import.form.php') . '?profiles_id=' . $profileId); }
    global $DB; $repository = new RunRepository(); $lock = 'tm_start_' . $profileId;
    if (!$DB->getLock($lock)) { Session::addMessageAfterRedirect(__('Another final import is being prepared for this profile.', 'ticketmigration'), false, WARNING); Html::redirect(WebUrl::front('import.form.php') . '?profiles_id=' . $profileId); }
    try {
        $activeRun = $repository->findActiveFinal($profileId);
        $runId = $activeRun !== null ? (int) $activeRun['id'] : $repository->createFinal($profile, $source, $totalRows);
    } finally { $DB->releaseLock($lock); }
    Html::redirect(WebUrl::front('run.form.php') . '?id=' . $runId);
}
Html::header(__('Prepare final import', 'ticketmigration'), $_SERVER['PHP_SELF'], 'tools', Menu::class);
Glpi\Application\View\TemplateRenderer::getInstance()->display('@ticketmigration/run/prepare.html.twig', [
    'profile' => $profile->fields, 'source' => $source->fields, 'total_rows' => $totalRows,
    'form_action' => WebUrl::front('import.form.php'), 'preview_url' => WebUrl::front('plan.preview.php') . '?profiles_id=' . $profileId,
]);
Html::footer();
