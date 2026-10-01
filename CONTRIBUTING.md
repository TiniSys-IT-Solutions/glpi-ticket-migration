# Contributing

Contributions are welcome through focused issues and pull requests.

## Before opening a pull request

1. Target the `main` branch and keep the change limited to one subject.
2. Do not commit migration payloads, credentials, database dumps or customer
   data.
3. Run `composer install`, `php vendor/bin/phpunit` and the available static
   checks.
4. Test imports only against a disposable GLPI environment.
5. Update `CHANGELOG.md` and the relevant document under `docs/`.

Changes to import execution must preserve the immutable plan, permission
checks, backup acknowledgement, audit trail and idempotency guarantees.

By contributing, you agree that your contribution is licensed under
GPL-3.0-or-later, the licence of this project.
