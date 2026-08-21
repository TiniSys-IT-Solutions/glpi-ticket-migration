<?php

include('../../../inc/includes.php');

use GlpiPlugin\Ticketmigration\Menu;
use GlpiPlugin\Ticketmigration\MigrationProfile;
use GlpiPlugin\Ticketmigration\ProfileRight;

Session::checkRight(ProfileRight::RIGHT_MANAGE_PROFILES, UPDATE);

$profile = new MigrationProfile();
if (isset($_POST['add'])) {
    $id = $profile->add($_POST);
    if ($id) {
        Html::redirect($profile::getFormURLWithID($id));
    }
} elseif (isset($_POST['update'])) {
    $profile->update($_POST);
    Html::back();
}

$id = (int) ($_GET['id'] ?? 0);
if ($id > 0 && (!$profile->getFromDB($id) || !$profile->canViewItem())) {
    Html::displayErrorAndDie(__('You do not have permission to view this migration profile.', 'ticketmigration'));
}

Html::header(__('Migration profile', 'ticketmigration'), $_SERVER['PHP_SELF'], 'tools', Menu::class);
Glpi\Application\View\TemplateRenderer::getInstance()->display(
    '@ticketmigration/profile/form.html.twig',
    [
        'profile' => $profile->fields,
        'profile_id' => $id,
        'form_action' => MigrationProfile::getFormURL(),
        'upload_url' => $CFG_GLPI['root_doc'] . '/plugins/ticketmigration/front/source.form.php',
        'active_entity' => Session::getActiveEntity(),
    ],
);
Html::footer();
