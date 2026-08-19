<?php

namespace GlpiPlugin\Ticketmigration\Tests\Unit\Source;

use GlpiPlugin\Ticketmigration\Source\CsvConfiguration;
use GlpiPlugin\Ticketmigration\Source\PreviewService;
use PHPUnit\Framework\TestCase;

final class PreviewServiceTest extends TestCase
{
    public function testPreviewIsBoundedAndHasFingerprint(): void
    {
        $path = dirname(__DIR__, 2) . '/fixtures/csv/multiline-duplicate-headers.csv';
        $result = (new PreviewService())->preview($path, new CsvConfiguration(), 1);
        self::assertCount(3, $result->columns);
        self::assertCount(1, $result->rows);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $result->schemaFingerprint);
    }
}
