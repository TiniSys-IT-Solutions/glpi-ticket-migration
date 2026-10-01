<?php

if (!defined('GLPI_ROOT')) { require dirname(__DIR__, 3) . '/inc/includes.php'; }

use GlpiPlugin\Ticketmigration\Execution\RunRepository;
use GlpiPlugin\Ticketmigration\MigrationProfile;
use GlpiPlugin\Ticketmigration\ProfileRight;

if (!ProfileRight::canViewHistory()) { Html::displayErrorAndDie(__('You do not have permission to perform this action.')); }
$runId = (int) ($_GET['id'] ?? 0); $run = (new RunRepository())->get($runId); $profile = new MigrationProfile();
if ($run === null || !$profile->getFromDB((int) $run['profiles_id']) || !$profile->canViewItem()) { Html::displayErrorAndDie(__('Migration run not found.', 'ticketmigration')); }
global $DB;
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="ticketmigration-run-' . $runId . '-trace.csv"');
$output = fopen('php://output', 'wb');
fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, ['row', 'external_id', 'status', 'tickets_id', 'message', 'warnings', 'information', 'validations', 'errors'], ';', '"', '\\');
$safe = static function ($value): string {
    $value = (string) $value;
    return preg_match('/^[=+\-@]/', $value) === 1 ? "'" . $value : $value;
};
foreach ($DB->request(['FROM' => 'glpi_plugin_ticketmigration_runitems', 'WHERE' => ['runs_id' => $runId], 'ORDER' => ['row_number ASC']]) as $item) {
    fputcsv($output, array_map($safe, [$item['row_number'], $item['external_id'], $item['status'], $item['tickets_id'], $item['message'], $item['warnings'], $item['information'], $item['validations'], $item['errors']]), ';', '"', '\\');
}
fclose($output);
