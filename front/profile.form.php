<?php

if (!defined('GLPI_ROOT')) {
    require dirname(__DIR__, 3) . '/inc/includes.php';
}

use GlpiPlugin\Ticketmigration\Menu;
use GlpiPlugin\Ticketmigration\MigrationProfile;
use GlpiPlugin\Ticketmigration\ProfileRight;
use GlpiPlugin\Ticketmigration\WebUrl;
use GlpiPlugin\Ticketmigration\SourceFile;

if (!ProfileRight::canManageProfiles(UPDATE)) {
    Html::displayErrorAndDie(__('You do not have permission to perform this action.'));
}

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
$activeSource = null;
if ($id > 0 && (int) ($profile->fields['sourcefiles_id'] ?? 0) > 0) {
    $source = new SourceFile();
    if ($source->getFromDB((int) $profile->fields['sourcefiles_id']) && $source->canViewItem()) {
        $activeSource = $source->fields;
    }
}

Html::header(__('Migration profile details', 'ticketmigration'), $_SERVER['PHP_SELF'], 'tools', Menu::class);
Glpi\Application\View\TemplateRenderer::getInstance()->display(
    '@ticketmigration/profile/form.html.twig',
    [
        'profile' => $profile->fields,
        'profile_id' => $id,
        'form_action' => MigrationProfile::getFormURL(),
        'upload_url' => WebUrl::front('source.form.php'),
        'sources_url' => WebUrl::front('source.php'),
        'mapping_url' => WebUrl::front('mapping.form.php'),
        'values_url' => WebUrl::front('value.form.php'),
        'preview_url' => WebUrl::front('preview.php'),
        'active_source' => $activeSource,
        'active_entity' => Session::getActiveEntity(),
    ],
);
Html::footer();
