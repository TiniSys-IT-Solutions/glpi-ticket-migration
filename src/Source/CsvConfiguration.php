<?php

namespace GlpiPlugin\Ticketmigration\Source;

final readonly class CsvConfiguration
{
    public function __construct(
        public string $delimiter = ';',
        public string $enclosure = '"',
        public string $escape = '',
        public bool $hasHeader = true,
        public string $encoding = 'UTF-8',
    ) {
        if (!in_array($delimiter, [';', ',', "\t"], true)) {
            throw new \InvalidArgumentException('Unsupported CSV delimiter.');
        }
    }
}
