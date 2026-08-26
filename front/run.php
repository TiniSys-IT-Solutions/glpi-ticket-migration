<?php

if (!defined('GLPI_ROOT')) {
    require dirname(__DIR__, 3) . '/inc/includes.php';
}

if (!\GlpiPlugin\Ticketmigration\ProfileRight::canViewHistory()) {
    Html::displayErrorAndDie(__('You do not have permission to perform this action.'));
}
$profileFilter = max(0, (int) ($_GET['profiles_id'] ?? 0));
$runs = array_values(array_filter((new \GlpiPlugin\Ticketmigration\Execution\RunRepository())->list(100, $profileFilter ?: null), static function (array $run): bool {
    $profile = new \GlpiPlugin\Ticketmigration\MigrationProfile();
    return $profile->getFromDB((int) $run['profiles_id']) && $profile->canViewItem();
}));
$labels = ['pilot' => __('Pilot', 'ticketmigration'), 'final' => __('Final', 'ticketmigration'), 'queued' => __('Queued', 'ticketmigration'), 'running' => __('Running', 'ticketmigration'), 'paused' => __('Paused', 'ticketmigration'), 'completed' => __('Completed', 'ticketmigration'), 'completed_with_issues' => __('Completed with issues', 'ticketmigration'), 'failed' => __('Failed', 'ticketmigration')];
foreach ($runs as &$run) {
    $run['mode_label'] = $labels[$run['mode']] ?? $run['mode']; $run['status_label'] = $labels[$run['status']] ?? $run['status'];
    $run['status_class'] = match ($run['status']) { 'completed' => 'bg-success', 'completed_with_issues' => 'bg-warning text-dark', 'failed' => 'bg-danger', 'running' => 'bg-primary', 'queued' => 'bg-info', default => 'bg-secondary' };
}
unset($run);
Html::header(__('Migration runs', 'ticketmigration'), $_SERVER['PHP_SELF'], 'tools', \GlpiPlugin\Ticketmigration\Menu::class);
Glpi\Application\View\TemplateRenderer::getInstance()->display('@ticketmigration/run/index.html.twig', [
    'runs' => $runs, 'run_url' => \GlpiPlugin\Ticketmigration\WebUrl::front('run.form.php') . '?id=',
    'profile_filter' => $profileFilter,
    'tabs' => [
        'current' => 'runs', 'dashboard_url' => \GlpiPlugin\Ticketmigration\WebUrl::front('config.php'),
        'profiles_url' => \GlpiPlugin\Ticketmigration\WebUrl::front('profile.php'), 'runs_url' => \GlpiPlugin\Ticketmigration\WebUrl::front('run.php'),
        'diagnostic_url' => \GlpiPlugin\Ticketmigration\WebUrl::front('config.php') . '?tab=diagnostic',
        'can_dashboard' => \GlpiPlugin\Ticketmigration\ProfileRight::canConfigure(), 'can_profiles' => \GlpiPlugin\Ticketmigration\ProfileRight::canViewProfiles(),
        'can_runs' => \GlpiPlugin\Ticketmigration\ProfileRight::canViewHistory(), 'can_diagnostic' => \GlpiPlugin\Ticketmigration\ProfileRight::canConfigure(),
    ],
]);
Html::footer();
