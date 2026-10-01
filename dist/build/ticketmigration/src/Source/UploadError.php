<?php

namespace GlpiPlugin\Ticketmigration\Source;

final class UploadError
{
    public static function describe(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE => __('The file exceeds the PHP upload_max_filesize limit.', 'ticketmigration'),
            UPLOAD_ERR_FORM_SIZE => __('The file exceeds the maximum size accepted by this form.', 'ticketmigration'),
            UPLOAD_ERR_PARTIAL => __('The file was only partially uploaded.', 'ticketmigration'),
            UPLOAD_ERR_NO_FILE => __('No file was uploaded.', 'ticketmigration'),
            UPLOAD_ERR_NO_TMP_DIR => __('The PHP upload temporary directory is missing.', 'ticketmigration'),
            UPLOAD_ERR_CANT_WRITE => __('PHP could not write the uploaded file to disk.', 'ticketmigration'),
            UPLOAD_ERR_EXTENSION => __('A PHP extension stopped the upload.', 'ticketmigration'),
            UPLOAD_ERR_OK => '',
            default => __('The CSV upload failed with an unknown error.', 'ticketmigration'),
        };
    }
}
