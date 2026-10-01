<?php

namespace GlpiPlugin\Ticketmigration\Source;

final class PreviewService
{
    public function __construct(private readonly SchemaFingerprint $fingerprint = new SchemaFingerprint()) {}

    public function preview(string $path, CsvConfiguration $configuration, int $limit = 10): PreviewResult
    {
        if ($limit < 1 || $limit > 100) {
            throw new \InvalidArgumentException('Preview limit must be between 1 and 100 rows.');
        }
        $reader = new CsvReader($path, $configuration);
        $columns = $reader->columns();
        $rows = $reader->preview($limit + 1);
        $isTruncated = count($rows) > $limit;
        if ($isTruncated) {
            array_pop($rows);
        }
        return new PreviewResult(
            $columns,
            $rows,
            $this->fingerprint->compute($columns, $configuration),
            $isTruncated,
        );
    }
}
