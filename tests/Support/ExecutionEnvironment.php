<?php

namespace {
    define('READ', 1);
    define('CREATE', 4);
    define('UPDATE', 2);
    define('GLPI_ROOT', '/tmp/disposable-glpi-test');

    final class Session
    {
        public static bool $entityAccess = true;
        public static function haveAccessToEntity(int $id): bool
        {
            return self::$entityAccess && $id >= 0;
        }
        public static function getLoginUserID(): int
        {
            return 1;
        }
        public static function checkCentralAccess(): void
        {
        }
    }

    final class Html
    {
        public static function displayErrorAndDie(string $message): never
        {
            throw new \RuntimeException('denied');
        }
        public static function redirect(string $url): never
        {
            throw new \RuntimeException('redirect');
        }
        public static function back(): never
        {
            throw new \RuntimeException('redirect');
        }
    }

    final class Ticket
    {
        public array $fields = [];
        public static bool $partialFailure = false;
        public static bool $visible = true;
        public static bool $createAllowed = true;
        public static function canCreate(): bool
        {
            return self::$createAllowed;
        }
        public function getFromDB(int $id): bool
        {
            global $DB;
            $this->fields = $DB->tables['tickets'][$id] ?? [];
            return $this->fields !== [];
        }
        public function can(int $id, int $right): bool
        {
            return self::$visible;
        }
        public function add(array $input): int
        {
            global $DB;
            if (!$DB->inTransaction || $DB->locks === []) {
                throw new \LogicException('Ticket creation must be transactional and locked.');
            }
            $DB->insert('tickets', $input);
            if (self::$partialFailure) {
                throw new \RuntimeException('Simulated partial lifecycle failure');
            }
            return $DB->insertId();
        }
    }
}

namespace Glpi\Error {
    final class ErrorHandler
    {
        public static function logCaughtException(\Throwable $exception): void
        {
        }
    }
}

namespace GlpiPlugin\Ticketmigration {
    final class ProfileRight
    {
        public static bool $runAllowed = true;
        public static array $manageRights = [CREATE => true, UPDATE => true];
        public static function canRunImports(): bool
        {
            return self::$runAllowed;
        }
        public static function canManageProfiles(int $right): bool
        {
            return self::$manageRights[$right] ?? false;
        }
    }

    final class MigrationProfile
    {
        public array $fields = ['id' => 7, 'is_archived' => 0];
        public array $input = [];
        public static bool $visible = true;
        public static int $writes = 0;
        public function getID(): int
        {
            return (int) $this->fields['id'];
        }
        public function canViewItem(): bool
        {
            return self::$visible;
        }
        public function getFromDB(int $id): bool
        {
            $this->fields['id'] = $id;
            return $id > 0;
        }
        public function canUpdateItem(): bool
        {
            return self::$visible && empty($this->fields['is_archived']) && ProfileRight::canManageProfiles(UPDATE);
        }
        public function canCreateItem(): bool
        {
            return ProfileRight::canManageProfiles(CREATE) && \Session::haveAccessToEntity((int) ($this->input['entities_id'] ?? 0));
        }
        public function add(array $input): int
        {
            self::$writes++;
            return 7;
        }
        public function update(array $input): bool
        {
            self::$writes++;
            return true;
        }
        public static function getFormURLWithID(int $id): string
        {
            return '/profile?id=' . $id;
        }
    }

    final class SourceFile
    {
        public array $fields = ['id' => 11, 'profiles_id' => 7, 'sha256' => 'snapshot', 'source_filename' => 'synthetic.csv', 'internal_filename' => 'synthetic.csv'];
        public function getID(): int
        {
            return (int) $this->fields['id'];
        }
        public function canViewItem(): bool
        {
            return true;
        }
    }
}

namespace GlpiPlugin\Ticketmigration\Tests\Support {
    final class MemoryDatabase
    {
        public array $tables = [];
        public array $locks = [];
        public bool $inTransaction = false;
        public bool $lockAvailable = true;
        public ?string $failInsert = null;
        public bool $failProgress = false;
        public bool $failCommit = false;
        private array $snapshot = [];
        private int $lastId = 0;

        public function request(array $query): \ArrayIterator
        {
            $rows = array_filter($this->tables[$query['FROM']] ?? [], static function (array $row) use ($query): bool {
                foreach ($query['WHERE'] ?? [] as $key => $value) {
                    if (($row[$key] ?? null) !== $value) {
                        return false;
                    }
                }
                return true;
            });
            return new \ArrayIterator(array_values($rows));
        }
        public function insert(string $table, array $row): bool
        {
            if ($table === $this->failInsert) {
                return false;
            }
            $this->lastId = count($this->tables[$table] ?? []) + 1;
            $row['id'] = $this->lastId;
            $this->tables[$table][$this->lastId] = $row;
            return true;
        }
        public function insertId(): int
        {
            return $this->lastId;
        }
        public function update(string $table, array $values, array $where): bool
        {
            if ($this->failProgress && isset($values['processed_rows'])) {
                return false;
            }
            foreach ($this->tables[$table] ?? [] as $id => $row) {
                if ($id === $where['id']) {
                    $this->tables[$table][$id] = array_replace($row, $values);
                }
            }
            return true;
        }
        public function beginTransaction(): void
        {
            if ($this->inTransaction) {
                throw new \LogicException('Unexpected nested transaction');
            }
            $this->snapshot = $this->tables;
            $this->inTransaction = true;
        }
        public function commit(): void
        {
            if ($this->failCommit) {
                throw new \RuntimeException('Simulated commit failure');
            }
            $this->inTransaction = false;
        }
        public function rollBack(): void
        {
            if (!$this->inTransaction) {
                throw new \LogicException('No transaction to roll back');
            }
            $this->tables = $this->snapshot;
            $this->inTransaction = false;
        }
        public function getLock(string $name): bool
        {
            if (!$this->lockAvailable || isset($this->locks[$name])) {
                return false;
            }
            $this->locks[$name] = true;
            return true;
        }
        public function releaseLock(string $name): bool
        {
            unset($this->locks[$name]);
            return true;
        }
    }
}
