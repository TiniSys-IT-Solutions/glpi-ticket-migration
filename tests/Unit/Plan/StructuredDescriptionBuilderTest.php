<?php

namespace GlpiPlugin\Ticketmigration\Tests\Unit\Plan;

use GlpiPlugin\Ticketmigration\Plan\StructuredDescriptionBuilder;
use GlpiPlugin\Ticketmigration\Source\SourceColumn;
use GlpiPlugin\Ticketmigration\Source\SourceRow;
use PHPUnit\Framework\TestCase;

final class StructuredDescriptionBuilderTest extends TestCase
{
    public function testConsolidatesMappedAndUnmappedFieldsAndEscapesHtml(): void
    {
        $html = (new StructuredDescriptionBuilder())->build(
            new SourceRow(2, ['EXT-1', '<script>alert(1)</script>', "Real\ndescription"]),
            [new SourceColumn(0, 'External ID'), new SourceColumn(1, 'Custom field'), new SourceColumn(2, 'Description')],
            [0 => ['target_key' => 'ticket.external_id', 'strategy' => 'direct'], 2 => ['target_key' => 'ticket.content', 'strategy' => 'direct']],
            "Real\ndescription",
            ['enabled' => true, 'include_mapped' => true, 'include_unmapped' => true, 'position' => 'before', 'excluded_columns' => []],
        );
        self::assertStringContainsString('External ID', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('<hr>', $html);
        self::assertSame(1, substr_count($html, 'Real<br'));
    }

    public function testHonoursColumnExclusions(): void
    {
        $html = (new StructuredDescriptionBuilder())->build(
            new SourceRow(2, ['secret']),
            [new SourceColumn(0, 'Secret')],
            [],
            '',
            ['excluded_columns' => [0]],
        );
        self::assertSame('', $html);
    }
}
