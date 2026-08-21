<?php

if (!defined('GLPI_ROOT')) {
    require dirname(__DIR__, 3) . '/inc/includes.php';
}

use GlpiPlugin\Ticketmigration\Install\SourceDirectory;
use GlpiPlugin\Ticketmigration\Menu;
use GlpiPlugin\Ticketmigration\MigrationProfile;
use GlpiPlugin\Ticketmigration\ProfileRight;
use GlpiPlugin\Ticketmigration\Source\CsvConfiguration;
use GlpiPlugin\Ticketmigration\Source\PreviewService;
use GlpiPlugin\Ticketmigration\Source\SourceFileStorage;
use GlpiPlugin\Ticketmigration\SourceFile;

if (!ProfileRight::canManageProfiles(UPDATE)) {
    Html::displayErrorAndDie(__('You do not have permission to perform this action.'));
}

$profileId = (int) ($_REQUEST['profiles_id'] ?? 0);
$profile = new MigrationProfile();
if (!$profile->getFromDB($profileId) || !$profile->canUpdateItem()) {
    Html::displayErrorAndDie(__('You do not have permission to update this migration profile.', 'ticketmigration'));
}

if (isset($_POST['upload'])) {
    $upload = $_FILES['source_csv'] ?? null;
    if (!is_array($upload) || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        Session::addMessageAfterRedirect(__('The CSV upload failed.', 'ticketmigration'), false, ERROR);
        Html::back();
    }

    try {
        $configuration = new CsvConfiguration(
            delimiter: (string) ($_POST['delimiter'] ?? ';'),
            hasHeader: isset($_POST['has_header']),
            encoding: (string) ($_POST['encoding'] ?? 'UTF-8'),
        );
        $stored = (new SourceFileStorage(SourceDirectory::path()))->storeUploaded(
            (string) $upload['tmp_name'],
            (string) $upload['name'],
        );
        $preview = (new PreviewService())->preview($stored->path, $configuration, 10);
        $source = new SourceFile();
        $sourceId = $source->add([
            'profiles_id' => $profileId,
            'users_id' => Session::getLoginUserID(),
            'source_filename' => $stored->sourceFilename,
            'internal_filename' => $stored->internalFilename,
            'sha256' => $stored->sha256,
            'filesize' => $stored->size,
            'mime_type' => $stored->mimeType,
            'schema_fingerprint' => $preview->schemaFingerprint,
            'uploaded_at' => date('Y-m-d H:i:s'),
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
        ]);
        if (!$sourceId) {
            unlink($stored->path);
            throw new RuntimeException('Unable to persist source file metadata.');
        }
        $profile->update([
            'id' => $profileId,
            'csv_config' => json_encode([
                'delimiter' => $configuration->delimiter,
                'has_header' => $configuration->hasHeader,
                'encoding' => $configuration->encoding,
            ], JSON_THROW_ON_ERROR),
        ]);
        Html::redirect($CFG_GLPI['root_doc'] . '/plugins/ticketmigration/front/preview.php?id=' . $sourceId);
    } catch (Throwable $exception) {
        ErrorHandler::logCaughtException($exception);
        Session::addMessageAfterRedirect(
            sprintf(__('Unable to prepare CSV preview: %s', 'ticketmigration'), $exception->getMessage()),
            false,
            ERROR,
        );
        Html::back();
    }
}

Html::header(__('Upload CSV source', 'ticketmigration'), $_SERVER['PHP_SELF'], 'tools', Menu::class);
Glpi\Application\View\TemplateRenderer::getInstance()->display(
    '@ticketmigration/source/upload.html.twig',
    [
        'profile' => $profile->fields,
        'profile_id' => $profileId,
        'form_action' => $CFG_GLPI['root_doc'] . '/plugins/ticketmigration/front/source.form.php',
    ],
);
Html::footer();
