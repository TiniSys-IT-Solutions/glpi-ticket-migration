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
        self::assertStringContainsString('missing@example.org', $plan->warnings[0]);
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

    public function testNormalizesDatesAndAppliesDeterministicEntityPrecedence(): void
    {
        $mappings = [
            0 => ['target_key' => 'ticket.external_id', 'strategy' => 'direct'],
            1 => ['target_key' => 'ticket.name', 'strategy' => 'direct'],
            2 => ['target_key' => 'ticket.date', 'strategy' => 'direct'],
            3 => ['target_key' => 'ticket.closedate', 'strategy' => 'direct'],
            4 => ['target_key' => 'ticket.location', 'strategy' => 'direct'],
            5 => ['target_key' => 'actor.requester', 'strategy' => 'direct'],
        ];
        $values = [
            'ticket.location' => [hash('sha256', 'Mende') => ['target_itemtype' => 'Location', 'target_id' => 30, 'target_value' => null]],
            'actor.requester' => [hash('sha256', 'requester@example.org') => ['target_itemtype' => 'User', 'target_id' => 50, 'target_value' => null]],
        ];
        $plan = (new MigrationPlanBuilder())->build(
            new SourceRow(2, ['EXT-47', 'Ticket', '11/08/2026', '11/08/2026 11:53', 'Mende', 'requester@example.org']),
            $mappings,
            $values,
            entityContext: ['default_entity_id' => 2, 'location_entities' => [30 => 7], 'user_entities' => [50 => [8]]],
        );
        self::assertSame('2026-08-11 00:00:00', $plan->ticket['date']);
        self::assertSame('2026-08-11 11:53:00', $plan->ticket['closedate']);
        self::assertSame(['itemtype' => 'Entity', 'id' => 7], $plan->ticket['entity']);
        self::assertTrue($plan->isExecutable());
    }

    public function testUsesProfileEntityWhenRequesterEntityIsAmbiguous(): void
    {
        $mappings = [
            0 => ['target_key' => 'ticket.external_id', 'strategy' => 'direct'],
            1 => ['target_key' => 'ticket.name', 'strategy' => 'direct'],
            2 => ['target_key' => 'actor.requester', 'strategy' => 'direct'],
        ];
        $values = ['actor.requester' => [hash('sha256', 'requester@example.org') => ['target_itemtype' => 'User', 'target_id' => 50, 'target_value' => null]]];
        $plan = (new MigrationPlanBuilder())->build(
            new SourceRow(2, ['EXT-48', 'Ticket', 'requester@example.org']),
            $mappings,
            $values,
            entityContext: ['default_entity_id' => 2, 'user_entities' => [50 => [7, 8]]],
        );
        self::assertSame(['itemtype' => 'Entity', 'id' => 2], $plan->ticket['entity']);
        self::assertStringContainsString('multiple entities', implode(' ', $plan->warnings));
    }
}
