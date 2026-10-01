<?php

namespace GlpiPlugin\Ticketmigration\Source;

final readonly class StoredSourceFile
{
    public function __construct(
        public string $sourceFilename,
        public string $internalFilename,
        public string $path,
        public string $sha256,
        public int $size,
        public string $mimeType,
    ) {}
}
