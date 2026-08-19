<?php

namespace GlpiPlugin\Ticketmigration\Tests\Unit;

use GlpiPlugin\Ticketmigration\Source\CsvReader;
use PHPUnit\Framework\TestCase;

final class CsvReaderTest extends TestCase
{
    public function testReadsBomDuplicateHeadersAndMultilineCell(): void
    {
        $path = dirname(__DIR__) . '/fixtures/csv/multiline-duplicate-headers.csv';
        $reader = new CsvReader($path);
        self::assertSame(['0:name', '1:name', '2:description'], array_map(fn ($column) => $column->key(), $reader->columns()));
        self::assertSame("line 1\nline 2", $reader->preview(1)[0]->value(2));
    }
}
