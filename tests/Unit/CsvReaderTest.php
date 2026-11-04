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

    public function testCountsAndReadsStableBatches(): void
    {
        $reader = new CsvReader(dirname(__DIR__) . '/fixtures/csv/generic-helpdesk-acceptance.csv');
        self::assertSame(2, $reader->countRows());
        self::assertSame([3], array_map(static fn ($row) => $row->number, $reader->batch(1, 25)));
        self::assertSame([], $reader->batch(2, 25));
    }

    public function testReadsEveryRowOfALargeSourceThroughSuccessiveBatches(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'ticketmigration_csv_');
        self::assertNotFalse($path);
        $handle = fopen($path, 'wb');
        self::assertIsResource($handle);
        fwrite($handle, "external_id;title\n");
        for ($index = 1; $index <= 6120; $index++) {
            fwrite($handle, sprintf("EXT-%d;Synthetic ticket %d\n", $index, $index));
        }
        fclose($handle);

        try {
            $reader = new CsvReader($path);
            self::assertSame(6120, $reader->countRows());
            $offset = 0;
            $rowNumbers = [];
            while (($batch = $reader->batch($offset, 10)) !== []) {
                array_push($rowNumbers, ...array_map(static fn ($row) => $row->number, $batch));
                $offset += count($batch);
            }
            self::assertCount(6120, $rowNumbers);
            self::assertSame(2, $rowNumbers[0]);
            self::assertSame(6121, $rowNumbers[6119]);
            self::assertSame(6120, count(array_unique($rowNumbers)));
        } finally {
            unlink($path);
        }
    }
}
