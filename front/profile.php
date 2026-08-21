<?php

include('../../../inc/includes.php');

use GlpiPlugin\Ticketmigration\Menu;
use GlpiPlugin\Ticketmigration\MigrationProfile;
use GlpiPlugin\Ticketmigration\ProfileRight;

Session::checkRight(ProfileRight::RIGHT_VIEW_PROFILES, READ);

global $DB;
$profiles = [];
foreach ($DB->request(['FROM' => MigrationProfile::getTable(), 'ORDER' => ['name']]) as $data) {
    $profile = new MigrationProfile();
    $profile->fields = $data;
    if ($profile->canViewItem()) {
        $profiles[] = $data;
    }
}

Html::header(__('Ticket Migration', 'ticketmigration'), $_SERVER['PHP_SELF'], 'tools', Menu::class);
Glpi\Application\View\TemplateRenderer::getInstance()->display(
    '@ticketmigration/profile/index.html.twig',
    [
        'profiles' => $profiles,
        'can_create' => Session::haveRight(ProfileRight::RIGHT_MANAGE_PROFILES, CREATE),
        'form_url' => MigrationProfile::getFormURL(),
    ],
);
Html::footer();
