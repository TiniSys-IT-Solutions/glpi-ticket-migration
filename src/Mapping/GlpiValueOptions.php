<?php

namespace GlpiPlugin\Ticketmigration\Mapping;

final class GlpiValueOptions
{
    private array $referenceCache = [];
    public function enum(string $kind): array
    {
        return match ($kind) {
            'status' => \Ticket::getAllStatusArray(),
            'priority' => $this->scale(static fn (int $value): string => \CommonITILObject::getPriorityName($value)),
            'urgency' => $this->scale(static fn (int $value): string => \CommonITILObject::getUrgencyName($value)),
            'impact' => $this->scale(static fn (int $value): string => \CommonITILObject::getImpactName($value)),
            'type' => [\Ticket::INCIDENT_TYPE => \Ticket::getTicketTypeName(\Ticket::INCIDENT_TYPE), \Ticket::DEMAND_TYPE => \Ticket::getTicketTypeName(\Ticket::DEMAND_TYPE)],
            default => [],
        };
    }

    public function exactReferences(string $itemtype, string $sourceValue): array
    {
        global $DB;
        if (!is_a($itemtype, \CommonDBTM::class, true)) {
            return [];
        }
        $table = $itemtype::getTable();
        if (!isset($this->referenceCache[$itemtype])) {
            $fields = $itemtype === 'User'
                ? ['id', 'name', 'realname', 'firstname']
                : ['id', 'name', 'completename'];
            $this->referenceCache[$itemtype] = iterator_to_array($DB->request([
                'SELECT' => $fields,
                'FROM' => $table,
                'LIMIT' => 5000,
            ]));
        }
        $needle = $this->normalize($sourceValue);
        $ids = [];
        foreach ($this->referenceCache[$itemtype] as $row) {
            $names = $itemtype === 'User'
                ? [$row['name'], $row['realname'], $row['firstname'], trim($row['firstname'] . ' ' . $row['realname']), trim($row['realname'] . ' ' . $row['firstname'])]
                : [$row['name'], $row['completename']];
            foreach ($names as $name) {
                if ($name !== null && $this->normalize((string) $name) === $needle) {
                    $ids[] = (int) $row['id'];
                    break;
                }
            }
        }
        if ($itemtype === 'User' && filter_var($sourceValue, FILTER_VALIDATE_EMAIL)) {
            foreach ($DB->request(['SELECT' => ['users_id'], 'FROM' => 'glpi_useremails', 'WHERE' => ['email' => $sourceValue], 'LIMIT' => 10]) as $row) {
                $ids[] = (int) $row['users_id'];
            }
        }
        $options = [];
        foreach (array_unique($ids) as $id) {
            $item = new $itemtype();
            if ($item->getFromDB($id) && $item->canViewItem()) {
                $options[$id] = $itemtype === 'User'
                    ? sprintf('%s — %s (#%d)', $item->getName(), (string) $item->fields['name'], $id)
                    : $item->getName();
            }
        }
        return $options;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        return preg_replace('/[^a-z0-9@._-]+/', ' ', $ascii !== false ? $ascii : $value) ?? $value;
    }

    private function scale(callable $label): array
    {
        $values = [];
        for ($value = 1; $value <= 5; $value++) {
            $values[$value] = $label($value);
        }
        return $values;
    }
}
