# Changelog

All notable public changes to GLPI Ticket Migration are documented here.

## [0.2.0] - 2026-11-04

### Changed

- Target GLPI 12.0.x and PHP 8.3 or newer on the dedicated migration branch.
- Match native typed model properties and use PHP class names for item types.
- Validate native model loading, plugin lifecycle and synthetic regressions against GLPI 12.0.1.

## [0.1.2] - 2026-10-07

### Fixed

- Commit each final ticket, import reference, row trace and progress together;
  a failed persistence operation rolls back creation before retry.
- Serialize pilot and final imports for the same migration profile, including
  different runs and workers recovering an expired batch lease.
- Check the selected profile's visibility and update permission before saving;
  creation requires CREATE permission and access to the selected entity.
- Recheck import authorization, archive state and source/run ownership server-side.

### Changed

- Second passes keep registered tickets unchanged, including modified source rows.
  Missing, deleted or inaccessible registered tickets are reported for review
  and are never replaced automatically.
- Reject external identifiers longer than the supported 255 characters.
- Distribute `ticketmigration-VERSION.zip` with the `ticketmigration/` root;
  use an explicit runtime allowlist to exclude private development files.
- Include build tooling and synthetic regression tests in the source repository.
- Generate Composer dependencies during installation and release packaging; keep
  generated development autoload maps out of the source repository.
- Correct version and implemented-feature documentation.

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
