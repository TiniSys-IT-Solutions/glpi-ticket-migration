# Ticket Migration

Ticket Migration is an autonomous GLPI 11 plugin for reconstructing historical tickets from reusable, user-configured CSV mappings. A source row becomes a `MigrationPlan` that may contain a ticket, actors, timeline entries, documents, relations, and an external reference.

Status: **early development (`0.0.x`)**. The current milestone provides the installable foundation, persistent schema, rights/menu model, streaming CSV reader, canonical row hashing, and the first domain object. It is not yet safe for production imports.

## Compatibility

- GLPI `>= 11.0.0` and `< 11.1.0`
- Primary validation target: GLPI `11.0.8`
- PHP `>= 8.2`

## Development setup

```bash
composer install
composer test
```

For integration testing, install this repository as `plugins/ticketmigration` in a disposable GLPI 11.0.8 instance. Never test historical migration against production data and keep notifications disabled in the test instance.

## Scope and limitations

CSV is the only V1 source. The project contains no TimeTonic, Jira, or other vendor-specific connector. Import execution, mapping UI, resolvers, attachments, and resumable batch processing remain under development.

## Documentation

- [Architecture](docs/ARCHITECTURE.md)
- [Migration flow](docs/MIGRATION_FLOW.md)
- [Mapping engine](docs/MAPPING_ENGINE.md)
- [Security](docs/SECURITY.md)
- [References](docs/REFERENCES.md)

## License

GPL-3.0-or-later. See [LICENSE](LICENSE).
