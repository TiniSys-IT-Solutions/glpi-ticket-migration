<?php

namespace GlpiPlugin\Ticketmigration\Tests\Unit\Plan;

use GlpiPlugin\Ticketmigration\Plan\MigrationPlanBuilder;
use GlpiPlugin\Ticketmigration\Source\SourceRow;
use PHPUnit\Framework\TestCase;

final class MigrationPlanBuilderTest extends TestCase
{
    public function testBuildsTicketAndResolvedStatusWithoutWritingGlpi(): void
    {
        $mappings = [
            0 => ['target_key' => 'ticket.external_id', 'strategy' => 'direct'],
            1 => ['target_key' => 'ticket.name', 'strategy' => 'direct'],
            2 => ['target_key' => 'ticket.status', 'strategy' => 'direct'],
        ];
        $status = ['ticket.status' => [hash('sha256', 'Closed') => ['target_itemtype' => null, 'target_id' => null, 'target_value' => '6']]];
        $plan = (new MigrationPlanBuilder())->build(new SourceRow(2, ['EXT-42', 'Archived ticket', 'Closed']), $mappings, $status);
        self::assertSame('EXT-42', $plan->externalReference['external_id']);
        self::assertSame('Archived ticket', $plan->ticket['name']);
        self::assertSame('6', $plan->ticket['status']);
        self::assertTrue($plan->isExecutable());
    }
}
