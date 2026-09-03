<?php

if (!defined('GLPI_ROOT')) { require dirname(__DIR__, 3) . '/inc/includes.php'; }

use Glpi\Error\ErrorHandler;
use GlpiPlugin\Ticketmigration\Execution\BatchProgressException;
use GlpiPlugin\Ticketmigration\Execution\MassImportService;
use GlpiPlugin\Ticketmigration\Execution\RunRepository;
use GlpiPlugin\Ticketmigration\MigrationProfile;
use GlpiPlugin\Ticketmigration\ProfileRight;
use GlpiPlugin\Ticketmigration\Source\CsvConfiguration;
use GlpiPlugin\Ticketmigration\Source\CsvReader;
use GlpiPlugin\Ticketmigration\SourceFile;

header('Content-Type: application/json; charset=UTF-8');
Html::header_nocache();
Session::checkCentralAccess();
$respond = static function (array $payload, int $status = 200): never { http_response_code($status); echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; };
if (!ProfileRight::canViewHistory() || !ProfileRight::canRunImports() || !Ticket::canCreate()) { $respond(['error' => __('You do not have permission to import tickets.', 'ticketmigration')], 403); }
$runId = (int) ($_POST['id'] ?? 0); $repository = new RunRepository(); $run = $repository->get($runId);
if ($run === null) { $respond(['error' => __('Migration run not found.', 'ticketmigration')], 404); }
$profile = new MigrationProfile();
if (!$profile->getFromDB((int) $run['profiles_id']) || !$profile->canViewItem()) { $respond(['error' => __('You do not have permission to view this migration profile.', 'ticketmigration')], 403); }
$source = new SourceFile();
if (!$source->getFromDB((int) $run['sourcefiles_id']) || !$source->canViewItem()) { $respond(['error' => __('The source snapshot for this run is unavailable.', 'ticketmigration')], 404); }
if (!in_array($run['status'], ['queued', 'running'], true)) { $respond(['status' => $run['status'], 'finished' => in_array($run['status'], ['completed', 'completed_with_issues'], true)]); }
$token = bin2hex(random_bytes(32));
if (!$repository->claimBatch($runId, $token)) { $respond(['status' => $run['status'], 'busy' => true], 409); }
try {
    $config = json_decode((string) $source->fields['csv_config'], true) ?: [];
    $reader = new CsvReader($source->getProtectedPath(), new CsvConfiguration(delimiter: (string) ($config['delimiter'] ?? ';'), hasHeader: (bool) ($config['has_header'] ?? true), encoding: (string) ($config['encoding'] ?? 'UTF-8')));
    $run = (new MassImportService())->process($runId, $profile, $source, $reader, 10);
    $repository->update($runId, ['last_error_code' => null, 'last_error_message' => null]);
} catch (BatchProgressException $exception) {
    ErrorHandler::logCaughtException($exception);
    $batchError = $exception->reason === 'source_exhausted'
        ? sprintf(__('No CSV row could be read at offset %1$d although this run expects %2$d rows. The import was paused without creating or skipping a ticket.', 'ticketmigration'), $exception->offset, $exception->totalRows)
        : sprintf(__('The migration batch could not advance beyond offset %1$d of %2$d. The import was paused without repeating a completed row.', 'ticketmigration'), $exception->offset, $exception->totalRows);
    $repository->update($runId, ['status' => 'paused', 'last_error_code' => $exception->reason, 'last_error_message' => $batchError]);
} catch (Throwable $exception) {
    ErrorHandler::logCaughtException($exception);
    $batchError = __('The batch was paused after a technical error. No completed line will be repeated.', 'ticketmigration');
    $repository->update($runId, ['status' => 'paused', 'last_error_code' => 'technical_failure', 'last_error_message' => $batchError]);
} finally { $repository->releaseBatch($runId, $token); }
if (isset($batchError)) { $respond(['status' => 'paused', 'error' => $batchError], 500); }
$labels = ['queued' => __('Queued', 'ticketmigration'), 'running' => __('Running', 'ticketmigration'), 'paused' => __('Paused', 'ticketmigration'), 'completed' => __('Completed', 'ticketmigration'), 'completed_with_issues' => __('Completed with issues', 'ticketmigration'),
    'success' => __('Imported', 'ticketmigration'), 'skipped' => __('Already imported', 'ticketmigration'), 'changed' => __('Changed', 'ticketmigration'), 'failed' => __('Failed', 'ticketmigration'),
    'ticket_imported' => __('Ticket imported', 'ticketmigration'), 'already_imported' => __('External identifier already imported', 'ticketmigration'), 'source_changed_after_import' => __('Source row changed after import', 'ticketmigration'),
    'plan_blocked' => __('Migration plan blocked', 'ticketmigration'), 'target_entity_forbidden' => __('Target entity forbidden', 'ticketmigration'), 'ticket_creation_failed' => __('Ticket creation failed', 'ticketmigration')];
$items = [];
foreach ($repository->recentItems($runId, 20) as $item) { $items[] = ['row_number' => (int) $item['row_number'], 'external_id' => (string) ($item['external_id'] ?? ''), 'status' => (string) $item['status'],
    'status_label' => $labels[$item['status']] ?? $item['status'], 'tickets_id' => (int) ($item['tickets_id'] ?? 0), 'ticket_url' => !empty($item['tickets_id']) ? Ticket::getFormURLWithID((int) $item['tickets_id']) : null,
    'message_label' => $labels[$item['message']] ?? $item['message']]; }
$respond(['status' => $run['status'], 'status_label' => $labels[$run['status']] ?? $run['status'], 'finished' => in_array($run['status'], ['completed', 'completed_with_issues'], true),
    'processed_rows' => (int) $run['processed_rows'], 'total_rows' => (int) $run['total_rows'], 'success_count' => (int) $run['success_count'],
    'skipped_count' => (int) $run['skipped_count'], 'changed_count' => (int) $run['changed_count'], 'failed_count' => (int) $run['failed_count'], 'items' => $items]);
