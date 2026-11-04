<?php

namespace GlpiPlugin\Ticketmigration\Tests\Unit\Mapping;

use GlpiPlugin\Ticketmigration\Mapping\DistinctValueCollector;
use GlpiPlugin\Ticketmigration\Source\SourceColumn;
use GlpiPlugin\Ticketmigration\Source\SourceReaderInterface;
use GlpiPlugin\Ticketmigration\Source\SourceRow;
use PHPUnit\Framework\TestCase;

final class DistinctValueCollectorTest extends TestCase
{
    public function testCollectsTrimmedDistinctValuesAndReportsLimit(): void
    {
        $reader = new class implements SourceReaderInterface {
            public function rows(): iterable { yield new SourceRow(1, [' Open ', 'a']); yield new SourceRow(2, ['Open', 'b']); yield new SourceRow(3, ['Closed', 'c']); }
            public function columns(): array { return [new SourceColumn(0, 'Status')]; }
            public function preview(int $limit = 10): array { return []; }
        };
        $set = (new DistinctValueCollector())->collect($reader, [0], 1)[0];
        self::assertSame(['Open'], $set->values);
        self::assertTrue($set->truncated);
    }

    public function testSplitsMultipleActorsWithExplicitCommaDelimiter(): void
    {
        $reader = new class implements SourceReaderInterface {
            public function rows(): iterable { yield new SourceRow(1, ['one@example.org, two@example.org']); }
            public function columns(): array { return [new SourceColumn(0, 'Technicians')]; }
            public function preview(int $limit = 10): array { return []; }
        };
        $set = (new DistinctValueCollector())->collect($reader, [0], 200, [0 => 'comma'])[0];
        self::assertSame(['one@example.org', 'two@example.org'], $set->values);
        self::assertFalse($set->truncated);
    }

    public function testAutoSplitsCommaSeparatedEmailsButPreservesLastNameFirstName(): void
    {
        $collector = new DistinctValueCollector();
        self::assertSame(
            ['one@example.org', 'two@example.org'],
            $collector->splitValue('one@example.org, two@example.org', 'auto'),
        );
        self::assertSame(['Dupont, Jean'], $collector->splitValue('Dupont, Jean', 'auto'));
        self::assertSame(
            ['support agfa', 'pascale@example.org'],
            $collector->splitValue('support agfa, pascale@example.org', 'auto'),
        );
        self::assertSame(
            ['francois@example.org'],
            $collector->splitValue('francois@example.org,', 'auto'),
        );
    }
}
