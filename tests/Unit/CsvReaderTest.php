<?php

namespace GlpiPlugin\Ticketmigration\Tests\Unit;

use GlpiPlugin\Ticketmigration\Source\CsvReader;
use GlpiPlugin\Ticketmigration\Source\CsvConfiguration;
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

    public function testProvidesAStableRowNavigationWindow(): void
    {
        $path = dirname(__DIR__) . '/fixtures/csv/generic-helpdesk-acceptance.csv';
        $reader = new CsvReader($path, new CsvConfiguration(delimiter: ';'));

        $first = $reader->rowWindow(0);
        self::assertSame(2, $first['row']?->number);
        self::assertNull($first['previous_offset']);
        self::assertSame(1, $first['next_offset']);

        $last = $reader->rowWindow(999);
        self::assertSame(3, $last['row']?->number);
        self::assertSame(0, $last['previous_offset']);
        self::assertNull($last['next_offset']);
        self::assertSame(1, $last['offset']);
    }

    public function testReadsSyntheticHelpdeskFixtureWithoutVendorSpecificCode(): void
    {
        $path = dirname(__DIR__) . '/fixtures/csv/generic-helpdesk-acceptance.csv';
        $reader = new CsvReader($path);
        self::assertCount(16, $reader->columns());
        $rows = $reader->preview(10);
        self::assertCount(2, $rows);
        self::assertStringContainsString("Description synthétique multiligne", (string) $rows[0]->value(11));
        self::assertSame('REQUESTER@EXAMPLE.ORG', $rows[0]->value(7));
    }
}
