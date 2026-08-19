<?php

namespace GlpiPlugin\Ticketmigration\Tests\Unit\Source;

use GlpiPlugin\Ticketmigration\Source\CsvConfiguration;
use GlpiPlugin\Ticketmigration\Source\SchemaFingerprint;
use GlpiPlugin\Ticketmigration\Source\SourceColumn;
use PHPUnit\Framework\TestCase;

final class SchemaFingerprintTest extends TestCase
{
    public function testFingerprintPreservesColumnPositionAndDuplicateNames(): void
    {
        $service = new SchemaFingerprint();
        $config = new CsvConfiguration();
        $first = $service->compute([new SourceColumn(0, 'name'), new SourceColumn(1, 'name')], $config);
        $second = $service->compute([new SourceColumn(0, 'name'), new SourceColumn(1, 'other')], $config);
        self::assertNotSame($first, $second);
        self::assertSame($first, $service->compute([new SourceColumn(0, 'name'), new SourceColumn(1, 'name')], $config));
    }
}
