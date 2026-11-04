<?php

namespace GlpiPlugin\Ticketmigration\Tests\Unit\Execution;

use GlpiPlugin\Ticketmigration\Execution\ImportLedgerRepository;
use GlpiPlugin\Ticketmigration\Execution\MassImportService;
use GlpiPlugin\Ticketmigration\Execution\PilotImportService;
use GlpiPlugin\Ticketmigration\MigrationProfile;
use GlpiPlugin\Ticketmigration\SourceFile;
use GlpiPlugin\Ticketmigration\Plan\MigrationPlan;
use GlpiPlugin\Ticketmigration\Source\CsvReader;
use GlpiPlugin\Ticketmigration\Source\CsvConfiguration;
use GlpiPlugin\Ticketmigration\Source\SourceRow;
use GlpiPlugin\Ticketmigration\Tests\Support\MemoryDatabase;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class ImportSafetyTest extends TestCase
{
    private string $csv;

    protected function setUp(): void
    {
        require dirname(__DIR__, 2) . '/Support/ExecutionEnvironment.php';
        $GLOBALS['DB'] = new MemoryDatabase();
        $this->csv = tempnam(sys_get_temp_dir(), 'tm-safe-');
        file_put_contents($this->csv, "id;title\n00042;Synthetic ticket\n");
        $this->createRun(1);
    }

    protected function tearDown(): void
    {
        unlink($this->csv);
        unset($GLOBALS['DB']);
    }

    private function createRun(int $id): void
    {
        $GLOBALS['DB']->tables['glpi_plugin_ticketmigration_runs'][$id] = [
            'id' => $id, 'profiles_id' => 7, 'sourcefiles_id' => 11, 'status' => 'queued',
            'backup_confirmed_at' => '2026-10-07 00:00:00', 'backup_confirmed_by' => 1,
            'source_hash' => 'snapshot', 'total_rows' => 1, 'current_offset' => 0,
            'processed_rows' => 0, 'success_count' => 0, 'skipped_count' => 0,
            'changed_count' => 0, 'failed_count' => 0, 'warning_count' => 0,
            'configuration_snapshot' => json_encode(['field_mappings' => [
                ['target_key' => 'ticket.external_id', 'strategy' => 'direct'],
                ['target_key' => 'ticket.name', 'strategy' => 'direct'],
            ]]),
        ];
    }

    private function process(int $id = 1): array
    {
        return (new MassImportService())->process($id, new MigrationProfile(), new SourceFile(), new CsvReader($this->csv, new CsvConfiguration()));
    }

    public function testSecondPassSkipsUnchangedAndReportsChangedWithoutCreating(): void
    {
        self::assertSame(1, $this->process()['success_count']);
        $this->createRun(2);
        self::assertSame(1, $this->process(2)['skipped_count']);
        file_put_contents($this->csv, "id;title\n00042;Changed synthetic ticket\n");
        $this->createRun(3);
        self::assertSame(1, $this->process(3)['changed_count']);
        self::assertCount(1, $GLOBALS['DB']->tables['tickets']);
        self::assertSame('00042', $GLOBALS['DB']->tables['tickets'][1]['externalid']);
        self::assertSame([], $GLOBALS['DB']->locks);
    }

    public function testSecondPassCreatesOnlyNewIdentifierIncludingZero(): void
    {
        $this->process();
        file_put_contents($this->csv, "id;title\n00042;Synthetic ticket\n0;New synthetic ticket\n");
        $this->createRun(2);
        $GLOBALS['DB']->tables['glpi_plugin_ticketmigration_runs'][2]['total_rows'] = 2;
        $result = $this->process(2);
        self::assertSame(1, $result['skipped_count']);
        self::assertSame(1, $result['success_count']);
        self::assertCount(2, $GLOBALS['DB']->tables['tickets']);
        self::assertSame('0', $GLOBALS['DB']->tables['glpi_plugin_ticketmigration_runitems'][3]['external_id']);
        self::assertSame('0', $GLOBALS['DB']->tables['tickets'][2]['externalid']);
    }

    public function testReferenceFailureRollsBackTicketAndAllowsSafeRetry(): void
    {
        $GLOBALS['DB']->failInsert = 'glpi_plugin_ticketmigration_externalrefs';
        try {
            $this->process();
            self::fail('Reference failure must abort');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('external reference', $exception->getMessage());
        }
        self::assertEmpty($GLOBALS['DB']->tables['tickets'] ?? []);
        self::assertEmpty($GLOBALS['DB']->tables['glpi_plugin_ticketmigration_runitems'] ?? []);
        self::assertSame(0, $GLOBALS['DB']->tables['glpi_plugin_ticketmigration_runs'][1]['current_offset']);
        self::assertSame([], $GLOBALS['DB']->locks);
        $GLOBALS['DB']->failInsert = null;
        self::assertSame(1, $this->process()['success_count']);
        self::assertCount(1, $GLOBALS['DB']->tables['tickets']);
    }

    public function testProgressFailureRollsBackTicketTraceAndReference(): void
    {
        $GLOBALS['DB']->failProgress = true;
        try {
            $this->process();
            self::fail('Progress failure must abort');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('progress', $exception->getMessage());
        }
        self::assertEmpty($GLOBALS['DB']->tables['tickets'] ?? []);
        self::assertEmpty($GLOBALS['DB']->tables['glpi_plugin_ticketmigration_externalrefs'] ?? []);
        self::assertEmpty($GLOBALS['DB']->tables['glpi_plugin_ticketmigration_runitems'] ?? []);
        self::assertSame([], $GLOBALS['DB']->locks);
    }

    public function testPartialGlpiFailureIsRolledBackBeforeRecordingFailedRow(): void
    {
        \Ticket::$partialFailure = true;
        self::assertSame(1, $this->process()['failed_count']);
        self::assertEmpty($GLOBALS['DB']->tables['tickets'] ?? []);
        self::assertEmpty($GLOBALS['DB']->tables['glpi_plugin_ticketmigration_externalrefs'] ?? []);
        self::assertSame('ticket_creation_failed', $GLOBALS['DB']->tables['glpi_plugin_ticketmigration_runitems'][1]['message']);
    }

    public function testMissingImportedTicketIsNotRecreated(): void
    {
        $this->process();
        unset($GLOBALS['DB']->tables['tickets'][1]);
        $this->createRun(2);
        self::assertSame(1, $this->process(2)['failed_count']);
        self::assertEmpty($GLOBALS['DB']->tables['tickets']);
        self::assertCount(1, $GLOBALS['DB']->tables['glpi_plugin_ticketmigration_externalrefs']);
        self::assertSame('imported_ticket_unavailable', $GLOBALS['DB']->tables['glpi_plugin_ticketmigration_runitems'][2]['message']);
    }

    public function testBusyProfilePreventsPilotAndFinalCreation(): void
    {
        $GLOBALS['DB']->locks[ImportLedgerRepository::lockName(7)] = true;
        $plan = new MigrationPlan(ticket: ['name' => 'Synthetic', 'entity' => ['id' => 0]], externalReference: ['external_id' => '00042']);
        $pilot = (new PilotImportService())->execute(new MigrationProfile(), new SourceFile(), new SourceRow(2, ['00042', 'Synthetic']), $plan);
        self::assertTrue($pilot->inProgress);
        try {
            $this->process();
            self::fail('Concurrent final import must abort');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('Another import', $exception->getMessage());
        }
        self::assertEmpty($GLOBALS['DB']->tables['tickets'] ?? []);
    }

    public function testPilotSuccessIsSkippedByFinalImport(): void
    {
        $plan = new MigrationPlan(ticket: ['name' => 'Synthetic ticket', 'entity' => ['id' => 0]], externalReference: ['external_id' => '00042']);
        $pilot = (new PilotImportService())->execute(new MigrationProfile(), new SourceFile(), new SourceRow(2, ['00042', 'Synthetic ticket']), $plan);
        self::assertSame(1, $pilot->ticketId);
        self::assertSame(1, $this->process()['skipped_count']);
        self::assertCount(1, $GLOBALS['DB']->tables['tickets']);
    }

    public function testCommitFailureRollsBackEverythingAndReleasesLock(): void
    {
        $GLOBALS['DB']->failCommit = true;
        try {
            $this->process();
            self::fail('Commit failure must abort');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('commit failure', $exception->getMessage());
        }
        self::assertEmpty($GLOBALS['DB']->tables['tickets'] ?? []);
        self::assertEmpty($GLOBALS['DB']->tables['glpi_plugin_ticketmigration_externalrefs'] ?? []);
        self::assertEmpty($GLOBALS['DB']->tables['glpi_plugin_ticketmigration_runitems'] ?? []);
        self::assertSame([], $GLOBALS['DB']->locks);
        self::assertFalse($GLOBALS['DB']->inTransaction);
    }

    public function testPilotReferenceFailureAlsoRollsBackTicket(): void
    {
        $GLOBALS['DB']->failInsert = 'glpi_plugin_ticketmigration_externalrefs';
        $plan = new MigrationPlan(ticket: ['name' => 'Synthetic', 'entity' => ['id' => 0]], externalReference: ['external_id' => '00042']);
        try {
            (new PilotImportService())->execute(new MigrationProfile(), new SourceFile(), new SourceRow(2, ['00042', 'Synthetic']), $plan);
            self::fail('Pilot reference failure must abort');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('pilot import history', $exception->getMessage());
        }
        self::assertEmpty($GLOBALS['DB']->tables['tickets'] ?? []);
        self::assertEmpty($GLOBALS['DB']->tables['glpi_plugin_ticketmigration_externalrefs'] ?? []);
        self::assertSame([], $GLOBALS['DB']->locks);
    }

    public function testDeletedImportedTicketIsNotRecreated(): void
    {
        $this->process();
        $GLOBALS['DB']->tables['tickets'][1]['is_deleted'] = 1;
        $this->createRun(2);
        self::assertSame(1, $this->process(2)['failed_count']);
        self::assertCount(1, $GLOBALS['DB']->tables['tickets']);
        self::assertCount(1, $GLOBALS['DB']->tables['glpi_plugin_ticketmigration_externalrefs']);
    }

    public function testUnauthorizedWorkerCannotCreate(): void
    {
        \Ticket::$createAllowed = false;
        try {
            $this->process();
            self::fail('Ticket CREATE permission required');
        } catch (\RuntimeException $exception) {
            self::assertSame('Import access denied.', $exception->getMessage());
        }
        self::assertEmpty($GLOBALS['DB']->tables['tickets'] ?? []);
        self::assertSame([], $GLOBALS['DB']->locks);
    }

    public function testKnownIdentifierWithBlockedPlanIsStillSkipped(): void
    {
        $this->process();
        $this->createRun(2);
        $snapshot = json_decode($GLOBALS['DB']->tables['glpi_plugin_ticketmigration_runs'][2]['configuration_snapshot'], true);
        $snapshot['field_mappings'][1]['target_key'] = 'actor.requester';
        $GLOBALS['DB']->tables['glpi_plugin_ticketmigration_runs'][2]['configuration_snapshot'] = json_encode($snapshot);
        self::assertSame(1, $this->process(2)['skipped_count']);
        self::assertCount(1, $GLOBALS['DB']->tables['tickets']);
    }

    public function testDifferentSourceProfileCannotExecuteRun(): void
    {
        $GLOBALS['DB']->tables['glpi_plugin_ticketmigration_runs'][1]['profiles_id'] = 9;
        $this->expectExceptionMessage('do not match');
        $this->process();
    }

    public function testProfileUpdateChecksPostedObjectBeforeWriting(): void
    {
        MigrationProfile::$visible = false;
        $_POST = ['update' => 1, 'id' => 99, 'name' => 'Unauthorized change'];
        try {
            require dirname(__DIR__, 3) . '/front/profile.form.php';
            self::fail('Invisible object must be denied');
        } catch (\RuntimeException $exception) {
            self::assertSame('denied', $exception->getMessage());
        }
        self::assertSame(0, MigrationProfile::$writes);
    }

    public function testCreateRightIsRequiredEvenForOperatorWithUpdateRight(): void
    {
        \GlpiPlugin\Ticketmigration\ProfileRight::$manageRights[CREATE] = false;
        $_POST = ['add' => 1, 'entities_id' => 0, 'name' => 'Synthetic profile'];
        try {
            require dirname(__DIR__, 3) . '/front/profile.form.php';
            self::fail('Create right required');
        } catch (\RuntimeException $exception) {
            self::assertSame('denied', $exception->getMessage());
        }
        self::assertSame(0, MigrationProfile::$writes);
    }
}
