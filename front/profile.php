<?php

if (!defined('GLPI_ROOT')) {
    require dirname(__DIR__, 3) . '/inc/includes.php';
}

use GlpiPlugin\Ticketmigration\Menu;
use GlpiPlugin\Ticketmigration\MigrationProfile;
use GlpiPlugin\Ticketmigration\ProfileRight;
use GlpiPlugin\Ticketmigration\SourceFile;
use GlpiPlugin\Ticketmigration\WebUrl;

if (!ProfileRight::canViewProfiles()) {
    Html::displayErrorAndDie(__('You do not have permission to perform this action.'));
}

global $DB;
$profiles = [];
foreach ($DB->request(['FROM' => MigrationProfile::getTable(), 'ORDER' => ['name']]) as $data) {
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
        $profiles[] = $data;
    }
}

Html::header(__('Ticket Migration', 'ticketmigration'), $_SERVER['PHP_SELF'], 'tools', Menu::class);
Glpi\Application\View\TemplateRenderer::getInstance()->display(
    '@ticketmigration/profile/index.html.twig',
    [
        'profiles' => $profiles,
        'can_create' => ProfileRight::canManageProfiles(CREATE),
        'form_url' => MigrationProfile::getFormURL(),
        'mapping_url' => WebUrl::front('mapping.form.php'),
        'values_url' => WebUrl::front('value.form.php'),
        'upload_url' => WebUrl::front('source.form.php'),
    ],
);
Html::footer();
