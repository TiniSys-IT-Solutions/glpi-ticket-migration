<?php

namespace GlpiPlugin\Ticketmigration\Source;

final class SourceFileStorage
{
    public const DEFAULT_MAX_BYTES = 524_288_000;

    public function __construct(
        private readonly string $directory,
        private readonly int $maxBytes = self::DEFAULT_MAX_BYTES,
    ) {}

    public function storeUploaded(string $temporaryPath, string $sourceFilename): StoredSourceFile
    {
        if (!is_uploaded_file($temporaryPath)) {
            throw new \RuntimeException('The source was not received through a valid HTTP upload.');
        }
        return $this->store($temporaryPath, $sourceFilename, true);
    }

    /** @internal Public for isolated tests and future CLI ingestion. */
    public function store(string $temporaryPath, string $sourceFilename, bool $moveUploaded = false): StoredSourceFile
    {
        if (!is_file($temporaryPath) || !is_readable($temporaryPath)) {
            throw new \RuntimeException('Uploaded source file is not readable.');
        }
        $safeSourceName = $this->sanitizeSourceName($sourceFilename);
        if (strtolower(pathinfo($safeSourceName, PATHINFO_EXTENSION)) !== 'csv') {
            throw new \InvalidArgumentException('Only CSV source files are supported.');
        }
        $size = filesize($temporaryPath);
        if ($size === false || $size <= 0 || $size > $this->maxBytes) {
            throw new \RuntimeException('Uploaded CSV is empty or exceeds the configured size limit.');
        }
        $this->ensureDirectory();
        $internalFilename = bin2hex(random_bytes(32)) . '.csv';
        $destination = $this->directory . DIRECTORY_SEPARATOR . $internalFilename;
        $moved = $moveUploaded
            ? move_uploaded_file($temporaryPath, $destination)
            : rename($temporaryPath, $destination);
        if (!$moved) {
            throw new \RuntimeException('Unable to move uploaded CSV into protected plugin storage.');
        }
        chmod($destination, 0640);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($destination) ?: 'application/octet-stream';
        $allowedMimeTypes = [
            'text/plain',
            'text/csv',
            'application/csv',
            'application/vnd.ms-excel',
            'application/octet-stream',
        ];
        if (!in_array($mime, $allowedMimeTypes, true)) {
            unlink($destination);
            throw new \RuntimeException('Uploaded file MIME type is not accepted for CSV sources.');
        }
        return new StoredSourceFile(
            $safeSourceName,
            $internalFilename,
            $destination,
            hash_file('sha256', $destination),
            $size,
            $mime,
        );
    }

    private function sanitizeSourceName(string $filename): string
    {
        $filename = str_replace(["\0", '\\'], ['', '/'], $filename);
        $filename = basename($filename);
        $filename = preg_replace('/[^\pL\pN._ -]+/u', '_', $filename) ?? '';
        $filename = trim($filename, ". \t\n\r\0\x0B");
        if ($filename === '') {
            throw new \InvalidArgumentException('Uploaded source filename is invalid.');
        }
        return mb_substr($filename, 0, 255);
    }

    private function ensureDirectory(): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0750, true) && !is_dir($this->directory)) {
            throw new \RuntimeException('Unable to create protected plugin storage.');
        }
        $realDirectory = realpath($this->directory);
        if ($realDirectory === false || !is_writable($realDirectory)) {
            throw new \RuntimeException('Protected plugin storage is not writable.');
        }
    }
}
