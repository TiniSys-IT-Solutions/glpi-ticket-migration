<?php

if (!defined('GLPI_ROOT')) { require dirname(__DIR__, 3) . '/inc/includes.php'; }

use GlpiPlugin\Ticketmigration\Execution\MassImportService;
use GlpiPlugin\Ticketmigration\Execution\RunRepository;
use GlpiPlugin\Ticketmigration\Menu;
use GlpiPlugin\Ticketmigration\MigrationProfile;
use GlpiPlugin\Ticketmigration\ProfileRight;
use GlpiPlugin\Ticketmigration\Source\CsvConfiguration;
use GlpiPlugin\Ticketmigration\Source\CsvReader;
use GlpiPlugin\Ticketmigration\SourceFile;
use GlpiPlugin\Ticketmigration\WebUrl;
use Glpi\Error\ErrorHandler;

if (!ProfileRight::canViewHistory()) { Html::displayErrorAndDie(__('You do not have permission to perform this action.')); }
$runId = (int) ($_REQUEST['id'] ?? 0); $repository = new RunRepository(); $run = $repository->get($runId);
if ($run === null) { Html::displayErrorAndDie(__('Migration run not found.', 'ticketmigration')); }
$profile = new MigrationProfile();
if (!$profile->getFromDB((int) $run['profiles_id']) || !$profile->canViewItem()) { Html::displayErrorAndDie(__('You do not have permission to view this migration profile.', 'ticketmigration')); }
$source = new SourceFile();
if (!$source->getFromDB((int) $run['sourcefiles_id']) || !$source->canViewItem()) { Html::displayErrorAndDie(__('The source snapshot for this run is unavailable.', 'ticketmigration')); }
$canControl = ProfileRight::canRunImports() && Ticket::canCreate();
if (isset($_POST['pause']) && $canControl && $run['status'] === 'running') { $repository->update($runId, ['status' => 'paused']); Html::redirect(WebUrl::front('run.form.php') . '?id=' . $runId); }
if (isset($_POST['resume']) && $canControl && $run['status'] === 'paused') { $repository->update($runId, ['status' => 'running', 'last_error_code' => null, 'last_error_message' => null]); Html::redirect(WebUrl::front('run.form.php') . '?id=' . $runId); }
if (isset($_POST['process_batch']) && $canControl && in_array($run['status'], ['queued', 'running'], true)) {
    $token = bin2hex(random_bytes(32));
    if ($repository->claimBatch($runId, $token)) {
        try {
            $config = json_decode((string) $source->fields['csv_config'], true) ?: [];
            $reader = new CsvReader($source->getProtectedPath(), new CsvConfiguration(delimiter: (string) ($config['delimiter'] ?? ';'), hasHeader: (bool) ($config['has_header'] ?? true), encoding: (string) ($config['encoding'] ?? 'UTF-8')));
            $processedRun = (new MassImportService())->process($runId, $profile, $source, $reader);
            if (in_array($processedRun['status'] ?? '', ['completed', 'completed_with_issues'], true)) {
                Session::addMessageAfterRedirect(sprintf(
                    __('Final import completed: %1$d imported, %2$d already imported, %3$d changed, %4$d failed.', 'ticketmigration'),
                    (int) $processedRun['success_count'], (int) $processedRun['skipped_count'],
                    (int) $processedRun['changed_count'], (int) $processedRun['failed_count'],
                ), false, INFO);
            }
        } catch (Throwable $exception) { ErrorHandler::logCaughtException($exception); $repository->update($runId, ['status' => 'paused']); Session::addMessageAfterRedirect(__('The batch was paused after a technical error. No completed line will be repeated.', 'ticketmigration'), false, ERROR); }
        finally { $repository->releaseBatch($runId, $token); }
    }
    Html::redirect(WebUrl::front('run.form.php') . '?id=' . $runId);
}
$run = $repository->get($runId); $items = $repository->recentItems($runId);
foreach ($items as &$item) { $item['ticket_url'] = !empty($item['tickets_id']) ? Ticket::getFormURLWithID((int) $item['tickets_id']) : null; }
unset($item);
$labels = [
    'queued' => __('Queued', 'ticketmigration'), 'running' => __('Running', 'ticketmigration'),
    'paused' => __('Paused', 'ticketmigration'), 'completed' => __('Completed', 'ticketmigration'),
    'completed_with_issues' => __('Completed with issues', 'ticketmigration'), 'failed' => __('Failed', 'ticketmigration'),
    'success' => __('Imported', 'ticketmigration'), 'skipped' => __('Already imported', 'ticketmigration'),
    'changed' => __('Changed', 'ticketmigration'),
    'ticket_imported' => __('Ticket imported', 'ticketmigration'), 'already_imported' => __('External identifier already imported', 'ticketmigration'),
    'source_changed_after_import' => __('Source row changed after import', 'ticketmigration'), 'plan_blocked' => __('Migration plan blocked', 'ticketmigration'),
    'target_entity_forbidden' => __('Target entity forbidden', 'ticketmigration'), 'ticket_creation_failed' => __('Ticket creation failed', 'ticketmigration'),
];
$run['status_label'] = $labels[$run['status']] ?? $run['status'];
foreach ($items as &$item) { $item['status_label'] = $labels[$item['status']] ?? $item['status']; $item['message_label'] = $labels[$item['message']] ?? $item['message']; }
unset($item);
$progress = (int) $run['total_rows'] > 0 ? min(100, round(100 * (int) $run['processed_rows'] / (int) $run['total_rows'])) : 100;
Html::header(__('Migration run', 'ticketmigration'), $_SERVER['PHP_SELF'], 'tools', Menu::class);
Glpi\Application\View\TemplateRenderer::getInstance()->display('@ticketmigration/run/progress.html.twig', [
    'run' => $run, 'profile' => $profile->fields, 'items' => $items, 'progress' => $progress, 'can_control' => $canControl,
    'form_action' => WebUrl::front('run.form.php'), 'history_url' => WebUrl::front('run.php'),
    'export_url' => WebUrl::front('run.export.php') . '?id=' . $runId,
    'batch_url' => WebUrl::ajax('run.batch.php'),
    'progress_script_url' => WebUrl::plugin() . '/public/js/run_progress.js',
]);
Html::footer();
