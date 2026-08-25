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

    public function testSplitsAndOmitsUnresolvedActorsWhenProfilePolicyAllowsIt(): void
    {
        $mappings = [
            0 => ['target_key' => 'ticket.external_id', 'strategy' => 'direct', 'configuration' => null],
            1 => ['target_key' => 'ticket.name', 'strategy' => 'direct', 'configuration' => null],
            2 => ['target_key' => 'actor.assignee', 'strategy' => 'direct', 'configuration' => json_encode(['multi_delimiter' => 'semicolon'])],
        ];
        $users = ['actor.assignee' => [hash('sha256', 'known@example.org') => ['target_itemtype' => 'User', 'target_id' => 42, 'target_value' => null]]];
        $plan = (new MigrationPlanBuilder())->build(
            new SourceRow(2, ['EXT-43', 'Ticket', 'known@example.org;missing@example.org']),
            $mappings,
            $users,
            resolutionConfiguration: ['skip_unresolved_targets' => ['actor.assignee']],
        );
        self::assertSame([['itemtype' => 'User', 'id' => 42]], $plan->actors['assignee']);
        self::assertCount(1, $plan->warnings);
        self::assertTrue($plan->isExecutable());
    }

    public function testGeneratesEmptyTitleFromDescriptionAndFallsBackToExternalId(): void
    {
        $mappings = [
            0 => ['target_key' => 'ticket.external_id', 'strategy' => 'direct'],
            1 => ['target_key' => 'ticket.name', 'strategy' => 'direct'],
            2 => ['target_key' => 'ticket.content', 'strategy' => 'direct'],
        ];
        $fromDescription = (new MigrationPlanBuilder())->build(
            new SourceRow(2, ['EXT-44', '', 'The printer no longer responds in accounting']),
            $mappings,
            [],
            titleFallbackConfiguration: ['enabled' => true, 'word_count' => 4],
        );
        self::assertSame('Ticket — The printer no longer…', $fromDescription->ticket['name']);
        self::assertTrue($fromDescription->isExecutable());

        $fromExternalId = (new MigrationPlanBuilder())->build(
            new SourceRow(3, ['EXT-45', '', '']),
            $mappings,
            [],
            titleFallbackConfiguration: ['enabled' => true],
        );
        self::assertSame('Ticket EXT-45', $fromExternalId->ticket['name']);
        self::assertTrue($fromExternalId->isExecutable());
    }

    public function testBuildsSeparateActorsFromAutoDetectedCommaSeparatedEmails(): void
    {
        $mappings = [
            0 => ['target_key' => 'ticket.external_id', 'strategy' => 'direct'],
            1 => ['target_key' => 'ticket.name', 'strategy' => 'direct'],
            2 => ['target_key' => 'actor.assignee', 'strategy' => 'direct', 'configuration' => json_encode(['multi_delimiter' => 'auto'])],
        ];
        $first = 'first@example.org';
        $second = 'second@example.org';
        $users = ['actor.assignee' => [
            hash('sha256', $first) => ['target_itemtype' => 'User', 'target_id' => 41, 'target_value' => null],
            hash('sha256', $second) => ['target_itemtype' => 'User', 'target_id' => 42, 'target_value' => null],
        ]];
        $plan = (new MigrationPlanBuilder())->build(
            new SourceRow(4, ['EXT-46', 'Ticket', $first . ', ' . $second]),
            $mappings,
            $users,
        );
        self::assertSame([
            ['itemtype' => 'User', 'id' => 41],
            ['itemtype' => 'User', 'id' => 42],
        ], $plan->actors['assignee']);
        self::assertTrue($plan->isExecutable());
    }
}
