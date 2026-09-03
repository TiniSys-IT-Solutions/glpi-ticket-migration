# GLPI Ticket Migration

![GLPI 11](https://img.shields.io/badge/GLPI-11.x-blue)
![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%2B-777bb4)
![License GPL-3.0](https://img.shields.io/badge/license-GPL--3.0--or--later-green)
![Status](https://img.shields.io/badge/status-early%20development-orange)

Ticket Migration is a GLPI 11 plugin for reconstructing historical tickets
from reusable, administrator-configured CSV mappings. A source row becomes an
immutable `MigrationPlan` that may contain a ticket, actors, timeline entries,
documents, relations and an external reference.

> Status: early development (`0.0.x`). Validate every workflow in a disposable
> environment before considering a production migration.

## Features

- Streaming CSV upload, preview and revision management.
- Reusable field mappings and controlled-value correspondence.
- Requester, technician, location and entity resolution with explicit review.
- Immutable row-by-row migration plans before execution.
- Pilot ticket creation and resumable final imports.
- Persistent run history, idempotency ledger and CSV trace export.
- Permission checks, audit metadata and mandatory backup acknowledgement.

## Screenshots

Official screenshots of the migration dashboard, mapping workflow, plan preview
and execution progress will be added to this section.

```text
Image to add: migration dashboard
Image to add: field and value mapping
Image to add: immutable plan preview
Image to add: final import progress
```

## Compatibility

- GLPI `>= 11.0.0` and `< 11.1.0`
- Primary validation target: GLPI `11.0.8`
- PHP `>= 8.2`

CSV is the only V1 source. The project contains no TimeTonic, Jira or other
vendor-specific connector.

## Installation and usage

Install the repository as `plugins/ticketmigration` in a disposable GLPI
instance, then install and enable it from `Setup > Plugins`.

The [user guide](docs/USER_GUIDE.md) covers permissions, profile creation, CSV
revisions, mappings, plan preview and final import execution.

Never begin a migration without a recent, verified and restorable backup of
the complete GLPI database and, when documents are involved, GLPI file
storage.

## Documentation

- [User guide](docs/USER_GUIDE.md)
- [Architecture](docs/ARCHITECTURE.md)
- [Migration flow](docs/MIGRATION_FLOW.md)
- [Mapping engine](docs/MAPPING_ENGINE.md)
- [Security model](docs/SECURITY.md)
- [Development](docs/DEVELOPMENT.md)
- [References](docs/REFERENCES.md)

## Development and tests

```bash
composer install
php vendor/bin/phpunit
```

Run integration tests only against a disposable GLPI instance. Never use
production data in fixtures, issues or pull requests. See
[CONTRIBUTING.md](CONTRIBUTING.md) for the expected checks and safety
invariants.

Installable ZIP archives are generated in the ignored local `dist/` directory
by `scripts/build-release.sh`. Tagged releases publish the matching archive as
a GitHub Release asset; generated packages are never committed to the source
repository.

## Security

Historical ticket data may contain personal, operational and confidential
information. Restrict access, protect uploaded CSV files and exported traces,
and follow the documented backup gate. Vulnerabilities must be reported
privately according to [SECURITY.md](SECURITY.md).

## Project identity

This plugin is independently developed and maintained by TiniSys IT Solutions.
GLPI is a trademark of its respective owners. This project is an independent
integration and is not an official GLPI product.

## Licence

GPL-3.0-or-later. See [LICENSE](LICENSE).
