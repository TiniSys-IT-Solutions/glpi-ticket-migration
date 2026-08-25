<?php

if (!defined('GLPI_ROOT')) {
    require dirname(__DIR__, 3) . '/inc/includes.php';
}

use GlpiPlugin\Ticketmigration\Menu;
use GlpiPlugin\Ticketmigration\MigrationProfile;
use GlpiPlugin\Ticketmigration\ProfileRight;
use GlpiPlugin\Ticketmigration\Source\SourceRevisionManager;
use GlpiPlugin\Ticketmigration\WebUrl;

if (!ProfileRight::canViewProfiles()) {
    Html::displayErrorAndDie(__('You do not have permission to perform this action.'));
}

$profileId = (int) ($_REQUEST['profiles_id'] ?? 0);
$profile = new MigrationProfile();
if (!$profile->getFromDB($profileId) || !$profile->canViewItem()) {
    Html::displayErrorAndDie(__('You do not have permission to view this migration profile.', 'ticketmigration'));
}
$manager = new SourceRevisionManager();
if (isset($_POST['activate']) && $profile->canUpdateItem()) {
    if (!$manager->activate($profile, (int) ($_POST['sourcefiles_id'] ?? 0))) {
        Session::addMessageAfterRedirect(__('Unable to select this CSV source.', 'ticketmigration'), false, ERROR);
    }
    Html::redirect(WebUrl::front('source.php') . '?profiles_id=' . $profileId);
}
if (isset($_POST['delete']) && $profile->canUpdateItem()) {
    if (!$manager->softDelete($profile, (int) ($_POST['sourcefiles_id'] ?? 0))) {
        Session::addMessageAfterRedirect(__('The active or previously used CSV source cannot be deleted.', 'ticketmigration'), false, ERROR);
    }
    Html::redirect(WebUrl::front('source.php') . '?profiles_id=' . $profileId);
}
$profile->getFromDB($profileId);
Html::header(__('CSV source revisions', 'ticketmigration'), $_SERVER['PHP_SELF'], 'tools', Menu::class);
Glpi\Application\View\TemplateRenderer::getInstance()->display('@ticketmigration/source/index.html.twig', [
    'profile' => $profile->fields,
    'sources' => $manager->revisions($profile),
    'can_manage' => $profile->canUpdateItem(),
    'form_action' => WebUrl::front('source.php'),
    'preview_url' => WebUrl::front('preview.php'),
    'upload_url' => WebUrl::front('source.form.php') . '?profiles_id=' . $profileId,
    'profile_url' => MigrationProfile::getFormURLWithID($profileId),
]);
Html::footer();
