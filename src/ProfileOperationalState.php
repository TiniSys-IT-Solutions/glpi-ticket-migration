<?php

namespace GlpiPlugin\Ticketmigration;

final class ProfileOperationalState
{
    public function summarize(array $profile, array $runs): array
    {
        usort($runs, static fn (array $left, array $right): int => (int) $right['id'] <=> (int) $left['id']);
        $pilots = array_values(array_filter($runs, static fn (array $run): bool => ($run['mode'] ?? '') === 'pilot' && ($run['status'] ?? '') === 'completed' && (int) ($run['success_count'] ?? 0) > 0));
        $finals = array_values(array_filter($runs, static fn (array $run): bool => ($run['mode'] ?? '') === 'final'));
        $latestFinal = $finals[0] ?? null;
        $activeFinal = current(array_filter($finals, static fn (array $run): bool => in_array($run['status'] ?? '', ['queued', 'running', 'paused'], true))) ?: null;

        if ($activeFinal !== null) {
            return [
                'key' => (string) $activeFinal['status'], 'run_id' => (int) $activeFinal['id'],
                'processed' => (int) $activeFinal['processed_rows'], 'total' => (int) $activeFinal['total_rows'],
                'pilot_count' => count($pilots),
            ];
        }
        if ($latestFinal !== null && ($latestFinal['status'] ?? '') === 'completed_with_issues') {
            return ['key' => 'completed_with_issues', 'run_id' => (int) $latestFinal['id'], 'failed' => (int) $latestFinal['failed_count'], 'changed' => (int) $latestFinal['changed_count'], 'pilot_count' => count($pilots)];
        }
        if ($latestFinal !== null && ($latestFinal['status'] ?? '') === 'completed') {
            return ['key' => 'completed', 'run_id' => (int) $latestFinal['id'], 'imported' => (int) $latestFinal['success_count'], 'pilot_count' => count($pilots)];
        }
        if ($latestFinal !== null && ($latestFinal['status'] ?? '') === 'failed') {
            return ['key' => 'failed', 'run_id' => (int) $latestFinal['id'], 'failed' => (int) $latestFinal['failed_count'], 'pilot_count' => count($pilots)];
        }
        if (count($pilots) > 0) {
            return ['key' => 'pilot_validated', 'run_id' => (int) $pilots[0]['id'], 'pilot_count' => count($pilots)];
        }
        return ['key' => ($profile['workflow_step'] ?? '') === 'values_configured' ? 'ready_for_pilot' : 'configuration_incomplete', 'run_id' => null, 'pilot_count' => 0];
    }
}
