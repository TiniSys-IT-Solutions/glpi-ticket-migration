<?php

if (!defined('GLPI_ROOT')) {
    require dirname(__DIR__, 3) . '/inc/includes.php';
}

use GlpiPlugin\Ticketmigration\Menu;
use GlpiPlugin\Ticketmigration\MigrationProfile;
use GlpiPlugin\Ticketmigration\ProfileRight;
use GlpiPlugin\Ticketmigration\ProfileOperationalState;
use GlpiPlugin\Ticketmigration\Execution\RunRepository;
use GlpiPlugin\Ticketmigration\SourceFile;
use GlpiPlugin\Ticketmigration\WebUrl;

if (!ProfileRight::canViewProfiles()) {
    Html::displayErrorAndDie(__('You do not have permission to perform this action.'));
}

global $DB;
$runsByProfile = [];
foreach ((new RunRepository())->list(null) as $run) { $runsByProfile[(int) $run['profiles_id']][] = $run; }
$showArchived = isset($_GET['archived']) && (int) $_GET['archived'] === 1;
$profiles = [];
foreach ($DB->request(['FROM' => MigrationProfile::getTable(), 'WHERE' => ['is_archived' => (int) $showArchived], 'ORDER' => ['name']]) as $data) {
    $profile = new MigrationProfile();
    $profile->fields = $data;
    if ($profile->canViewItem()) {
        $data['active_source'] = null;
        if ((int) ($data['sourcefiles_id'] ?? 0) > 0) {
            $activeSource = new SourceFile();
            if ($activeSource->getFromDB((int) $data['sourcefiles_id'])) {
                $data['active_source'] = $activeSource->fields;
            }
        }
        $data['massive_checkbox'] = Html::getMassiveActionCheckBox(MigrationProfile::class, (int) $data['id']);
        $data['operational_state'] = (new ProfileOperationalState())->summarize($data, $runsByProfile[(int) $data['id']] ?? []);
        $profiles[] = $data;
    }
}

Html::header(__('Ticket Migration', 'ticketmigration'), $_SERVER['PHP_SELF'], 'tools', Menu::class);
$profileActions = (new MigrationProfile())->getSpecificMassiveActions();
$massiveActions = $profileActions === [] ? '' : (string) Html::showMassiveActions([
    'num_displayed' => count($profiles),
    'container' => 'ticketmigration-profiles',
    'specific_actions' => $profileActions,
    'display' => false,
]);
Glpi\Application\View\TemplateRenderer::getInstance()->display(
    '@ticketmigration/profile/index.html.twig',
    [
        'profiles' => $profiles,
        'can_create' => ProfileRight::canManageProfiles(CREATE),
        'show_archived' => $showArchived,
        'massive_actions' => $massiveActions,
        'check_all' => $profileActions === [] ? '' : Html::getCheckAllAsCheckbox('ticketmigration-profiles'),
        'active_url' => WebUrl::front('profile.php'),
        'archived_url' => WebUrl::front('profile.php') . '?archived=1',
        'form_url' => MigrationProfile::getFormURL(),
        'mapping_url' => WebUrl::front('mapping.form.php'),
        'values_url' => WebUrl::front('value.form.php'),
        'upload_url' => WebUrl::front('source.form.php'),
        'preview_url' => WebUrl::front('plan.preview.php'),
        'import_url' => WebUrl::front('import.form.php'),
        'run_url' => WebUrl::front('run.form.php'),
        'runs_url' => WebUrl::front('run.php'),
        'sources_url' => WebUrl::front('source.php'),
        'tabs' => [
            'current' => 'profiles', 'dashboard_url' => WebUrl::front('config.php'),
            'profiles_url' => WebUrl::front('profile.php'), 'runs_url' => WebUrl::front('run.php'),
            'diagnostic_url' => WebUrl::front('config.php') . '?tab=diagnostic',
            'can_dashboard' => ProfileRight::canConfigure(), 'can_profiles' => ProfileRight::canViewProfiles(), 'can_runs' => ProfileRight::canViewHistory(), 'can_diagnostic' => ProfileRight::canConfigure(),
        ],
    ],
);
Html::footer();
