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
use GlpiPlugin\Ticketmigration\Source\UploadError;
use GlpiPlugin\Ticketmigration\SourceFile;
use GlpiPlugin\Ticketmigration\WebUrl;
use Glpi\Error\ErrorHandler;

if (!ProfileRight::canManageProfiles(UPDATE)) {
    Html::displayErrorAndDie(__('You do not have permission to perform this action.'));
}

$phpUploadLimit = Toolbox::getPhpUploadSizeLimit();
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && $phpUploadLimit > 0
    && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $phpUploadLimit) {
    Session::addMessageAfterRedirect(
        sprintf(
            __('The request exceeds the PHP upload limit of %s.', 'ticketmigration'),
            Toolbox::getSize($phpUploadLimit),
        ),
        false,
        ERROR,
    );
    Html::back();
}

$profileId = (int) ($_REQUEST['profiles_id'] ?? 0);
$profile = new MigrationProfile();
if (!$profile->getFromDB($profileId) || !$profile->canUpdateItem()) {
    Html::displayErrorAndDie(__('You do not have permission to update this migration profile.', 'ticketmigration'));
}

if (isset($_POST['upload'])) {
    $upload = $_FILES['source_csv'] ?? null;
    $uploadError = is_array($upload) ? (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) : UPLOAD_ERR_NO_FILE;
    if ($uploadError !== UPLOAD_ERR_OK) {
        Session::addMessageAfterRedirect(UploadError::describe($uploadError), false, ERROR);
        Html::redirect(WebUrl::front('source.form.php') . '?profiles_id=' . $profileId);
    }

    $stored = null;
    $sourceId = 0;
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
            'csv_config' => json_encode([
                'delimiter' => $configuration->delimiter,
                'has_header' => $configuration->hasHeader,
                'encoding' => $configuration->encoding,
            ], JSON_THROW_ON_ERROR),
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
        if (!(new \GlpiPlugin\Ticketmigration\Source\SourceRevisionManager())->activate($profile, $sourceId)) {
            throw new RuntimeException('Unable to select the uploaded CSV source.');
        }
    } catch (Throwable $exception) {
        ErrorHandler::logCaughtException($exception);
        if ($sourceId > 0) {
            global $DB;
            $DB->delete(SourceFile::getTable(), ['id' => $sourceId]);
        }
        if ($stored !== null && is_file($stored->path)) {
            unlink($stored->path);
        }
        Session::addMessageAfterRedirect(
            sprintf(__('Unable to prepare CSV preview: %s', 'ticketmigration'), $exception->getMessage()),
            false,
            ERROR,
        );
        Html::redirect(WebUrl::front('source.form.php') . '?profiles_id=' . $profileId);
    } finally {
        // GLPI and its Symfony debug profiler may rebuild the request from
        // superglobals during shutdown. DataInjection follows the same rule
        // after moving an uploaded file: remove the stale $_FILES entry.
        unset($_FILES['source_csv']);
    }

    Html::redirect(WebUrl::front('preview.php') . '?id=' . $sourceId);
}

Html::header(__('Upload CSV source', 'ticketmigration'), $_SERVER['PHP_SELF'], 'tools', Menu::class);
Glpi\Application\View\TemplateRenderer::getInstance()->display(
    '@ticketmigration/source/upload.html.twig',
    [
        'profile' => $profile->fields,
        'profile_id' => $profileId,
        'form_action' => WebUrl::front('source.form.php'),
        'profile_url' => MigrationProfile::getFormURLWithID($profileId),
        'max_upload_bytes' => $phpUploadLimit > 0
            ? min($phpUploadLimit, SourceFileStorage::DEFAULT_MAX_BYTES)
            : SourceFileStorage::DEFAULT_MAX_BYTES,
        'max_upload_size' => Toolbox::getSize(
            $phpUploadLimit > 0
                ? min($phpUploadLimit, SourceFileStorage::DEFAULT_MAX_BYTES)
                : SourceFileStorage::DEFAULT_MAX_BYTES,
        ),
    ],
);
Html::footer();
