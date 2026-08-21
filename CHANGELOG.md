# Changelog

All notable changes follow [Keep a Changelog](https://keepachangelog.com/) and Semantic Versioning.

## [Unreleased]

## [0.0.6] - 2026-08-21

### Fixed

- Register the Tools menu provider unconditionally and authorize its entries when GLPI builds the menu, including the GLPI administrator recovery path.
- Apply the same administrator recovery authorization to the currently testable profile, upload, preview, history, and dashboard pages.

## [0.0.5] - 2026-08-21

### Fixed

- Load GLPI safely from every front controller whether it is dispatched by GLPI 11 or accessed directly from a plugins/marketplace directory.

### Added

- Replace the configuration placeholder with an operational dashboard, direct workflow actions, counters, and protected-storage status.

## [0.0.4] - 2026-08-21

### Fixed

- Display the plugin configuration wrench while keeping authorization enforced by the target page.
- Bootstrap Ticket Migration rights once for GLPI administrator profiles so the Tools menu is immediately available.
- Render the Ticket Migration rights matrix from the profile being edited.

## [0.0.3] - 2026-08-21

### Fixed

- Make profile-right registration idempotent so interrupted installs and upgrades can be resumed safely.

## [0.0.2] - 2026-08-21

### Added

- Functional profile list/editor, protected CSV upload, persistent source metadata, and bounded streaming preview.

## [0.0.1] - 2026-08-19

### Added

- Initial GLPI 11 plugin metadata, install schema, rights, and Tools menu.
- Streaming CSV source abstraction with BOM, encoding, duplicate-header, and multiline support.
- Canonical source-row hashing and initial `MigrationPlan` value object.
- Migration profile item type, protected source-file storage, positional CSV schema fingerprints, and bounded preview service.
- Architecture, flow, mapping, security, development, user, and reference documentation.
- Reproducible verified archive and automated quality/release workflows.
