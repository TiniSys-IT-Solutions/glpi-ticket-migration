<?php

namespace GlpiPlugin\Ticketmigration\Tests\Unit;

use GlpiPlugin\Ticketmigration\ProfileOperationalState;
use PHPUnit\Framework\TestCase;

final class ProfileOperationalStateTest extends TestCase
{
    public function testReadyConfigurationBecomesPilotValidatedAfterSuccessfulPilot(): void
    {
        $service = new ProfileOperationalState();
        self::assertSame('ready_for_pilot', $service->summarize(['workflow_step' => 'values_configured'], [])['key']);
        $state = $service->summarize(['workflow_step' => 'values_configured'], [
            ['id' => 4, 'mode' => 'pilot', 'status' => 'completed', 'success_count' => 1],
            ['id' => 3, 'mode' => 'pilot', 'status' => 'completed', 'success_count' => 1],
        ]);
        self::assertSame('pilot_validated', $state['key']);
        self::assertSame(2, $state['pilot_count']);
    }

    public function testActiveFinalRunHasPriorityOverPilots(): void
    {
        $state = (new ProfileOperationalState())->summarize(['workflow_step' => 'values_configured'], [
            ['id' => 7, 'mode' => 'final', 'status' => 'running', 'processed_rows' => 1756, 'total_rows' => 3000],
            ['id' => 2, 'mode' => 'pilot', 'status' => 'completed', 'success_count' => 1],
        ]);
        self::assertSame('running', $state['key']);
        self::assertSame(1756, $state['processed']);
        self::assertSame(3000, $state['total']);
    }

    public function testLatestFinalResultIsReported(): void
    {
        $state = (new ProfileOperationalState())->summarize(['workflow_step' => 'values_configured'], [
            ['id' => 9, 'mode' => 'final', 'status' => 'completed_with_issues', 'failed_count' => 3, 'changed_count' => 2],
            ['id' => 8, 'mode' => 'final', 'status' => 'completed', 'success_count' => 25],
        ]);
        self::assertSame('completed_with_issues', $state['key']);
        self::assertSame(3, $state['failed']);
        self::assertSame(2, $state['changed']);
    }
}
