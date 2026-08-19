# Development instructions

This repository contains `ticketmigration`, a GLPI 11 plugin that reconstructs historical tickets from generic CSV exports.

## Invariants

- Never add source-vendor-specific fields or behavior. Qualification fixtures may resemble real exports but must be synthetic.
- One source row is converted into one immutable `MigrationPlan`; dry run and execution must use the same plan builder.
- Never use GLPI's REST API from inside GLPI. Use verified GLPI 11 internal classes.
- Never update core tables outside the dedicated, guarded `HistoricalMetadataRestorer` design.
- Fuzzy resolver results are suggestions only and require explicit user validation.
- Notifications are disabled per imported object; never mutate the global notification configuration.
- Large source files and row payloads must not be stored in PHP sessions.
- Update architecture/security/user documentation whenever behavior changes materially.

## Architecture

Dependencies flow inward: UI/controllers -> application services -> mapping/planning domain. Source readers are adapters. The executor is the only layer allowed to create GLPI business objects. Plugin repositories persist profiles, runs, items, and external references.

## Commands

```bash
composer install
vendor/bin/phpunit
vendor/bin/php-cs-fixer check --diff
vendor/bin/phpstan analyse
```

Integration tests target a disposable GLPI 11.0.8 installation with the folder name `ticketmigration`.
