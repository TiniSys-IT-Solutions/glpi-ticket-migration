# Changelog

All notable public changes to GLPI Ticket Migration are documented here.

Detailed pre-public iterations remain available in
[docs/DEVELOPMENT_HISTORY.md](docs/DEVELOPMENT_HISTORY.md).

## [0.1.1] - 2026-09-03

### Added

- Reusable CSV field and value mappings with immutable migration plans.
- Pilot ticket creation and resumable final imports.
- Persistent run history, idempotency ledger and diagnostic trace export.
- Explicit requester, location and target-entity resolution.

### Changed

- Establish the first clean public release baseline.
- Align release packaging with the other TiniSys GLPI plugins.
- Align licensing, ownership, security and contribution documentation with
  TiniSys IT Solutions standards.

### Security

- Require dedicated import rights, GLPI ticket creation rights and CSRF checks.
- Record an explicit backup acknowledgement before final imports.
- Protect stored CSV files, remote attachments and exported spreadsheet data.
