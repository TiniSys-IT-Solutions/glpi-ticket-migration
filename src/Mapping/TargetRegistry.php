<?php

namespace GlpiPlugin\Ticketmigration\Mapping;

final class TargetRegistry
{
    /** @return array<string, array{label: string, group: string, required?: bool}> */
    public static function definitions(): array
    {
        return [
            'ticket.external_id' => ['label' => __('External ticket identifier', 'ticketmigration'), 'group' => __('Ticket', 'ticketmigration'), 'required' => true],
            'ticket.name' => ['label' => __('Title', 'ticketmigration'), 'group' => __('Ticket', 'ticketmigration'), 'required' => true],
            'ticket.content' => ['label' => __('Description', 'ticketmigration'), 'group' => __('Ticket', 'ticketmigration')],
            'ticket.date' => ['label' => __('Opening date', 'ticketmigration'), 'group' => __('Ticket', 'ticketmigration')],
            'ticket.closedate' => ['label' => __('Closing date', 'ticketmigration'), 'group' => __('Ticket', 'ticketmigration')],
            'ticket.solvedate' => ['label' => __('Resolution date', 'ticketmigration'), 'group' => __('Ticket', 'ticketmigration')],
            'ticket.status' => ['label' => __('Status', 'ticketmigration'), 'group' => __('Ticket', 'ticketmigration')],
            'ticket.priority' => ['label' => __('Priority', 'ticketmigration'), 'group' => __('Ticket', 'ticketmigration')],
            'ticket.urgency' => ['label' => __('Urgency', 'ticketmigration'), 'group' => __('Ticket', 'ticketmigration')],
            'ticket.impact' => ['label' => __('Impact', 'ticketmigration'), 'group' => __('Ticket', 'ticketmigration')],
            'ticket.type' => ['label' => __('Type', 'ticketmigration'), 'group' => __('Ticket', 'ticketmigration')],
            'ticket.category' => ['label' => __('Category', 'ticketmigration'), 'group' => __('Classification', 'ticketmigration')],
            'ticket.location' => ['label' => __('Location', 'ticketmigration'), 'group' => __('Classification', 'ticketmigration')],
            'ticket.entity' => ['label' => __('Entity', 'ticketmigration'), 'group' => __('Classification', 'ticketmigration')],
            'actor.requester' => ['label' => __('Requester', 'ticketmigration'), 'group' => __('Actors', 'ticketmigration')],
            'actor.assignee' => ['label' => __('Assigned technician', 'ticketmigration'), 'group' => __('Actors', 'ticketmigration')],
            'actor.requester_group' => ['label' => __('Requester group', 'ticketmigration'), 'group' => __('Actors', 'ticketmigration')],
            'actor.assignee_group' => ['label' => __('Assigned group', 'ticketmigration'), 'group' => __('Actors', 'ticketmigration')],
            'timeline.followups' => ['label' => __('Followups', 'ticketmigration'), 'group' => __('Timeline', 'ticketmigration')],
            'timeline.tasks' => ['label' => __('Tasks', 'ticketmigration'), 'group' => __('Timeline', 'ticketmigration')],
            'timeline.solution' => ['label' => __('Solution', 'ticketmigration'), 'group' => __('Timeline', 'ticketmigration')],
            'documents.urls' => ['label' => __('Document URLs', 'ticketmigration'), 'group' => __('Documents', 'ticketmigration')],
        ];
    }

    public static function has(string $key): bool
    {
        return isset(self::definitions()[$key]);
    }

    public static function requiredKeys(): array
    {
        return array_keys(array_filter(self::definitions(), static fn (array $definition): bool => $definition['required'] ?? false));
    }
}
