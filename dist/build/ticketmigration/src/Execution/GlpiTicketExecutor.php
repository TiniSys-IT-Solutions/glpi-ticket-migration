<?php

namespace GlpiPlugin\Ticketmigration\Execution;

use GlpiPlugin\Ticketmigration\Plan\MigrationPlan;

final class GlpiTicketExecutor
{
    public function execute(MigrationPlan $plan): int
    {
        if (!$plan->isExecutable()) {
            throw new \RuntimeException('A migration plan containing errors cannot be executed.');
        }
        $ticket = new \Ticket();
        $ticketId = $ticket->add((new TicketInputBuilder())->build($plan));
        if ($ticketId === false || (int) $ticketId <= 0) {
            throw new \RuntimeException('GLPI refused ticket creation.');
        }
        return (int) $ticketId;
    }
}
