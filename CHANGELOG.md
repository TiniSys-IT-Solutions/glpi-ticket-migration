# Changelog

All notable changes follow [Keep a Changelog](https://keepachangelog.com/) and Semantic Versioning.

## [Unreleased]

## [0.0.17] - 2026-08-25

### Added

- Add configurable multi-actor splitting for requesters, assigned technicians, and actor groups.
- Add official GLPI AJAX selectors to search the complete user, location, group, entity, or category list without loading it into the page.
- Add an explicit per-actor-field policy to continue without that actor role when more than 200 distinct values are discovered.

### Changed

- Preselect a unique exact or normalized-exact match while still requiring the operator to save and validate the correspondence.
- Apply the same actor-splitting and unresolved-actor policy to distinct-value discovery and immutable migration-plan construction.
- Keep comma splitting explicit to avoid corrupting values written as `Last name, First name`; automatic mode recognizes semicolons, pipes, and line breaks.

## [0.0.16] - 2026-08-25

### Added

- Add configurable structured historical-description consolidation for mapped and unmapped CSV fields.
- Add per-column exclusion controls, mapped/unmapped inclusion policies, and before/after positioning around the main description.
- Add safe HTML generation with escaped source labels and values, omitted empty fields, preserved line breaks, section headings, and an `<hr>` separator.

### Changed

- Rename the direct description target to **Main description** and keep it as a dedicated section without duplicating it in historical metadata.
- Render the consolidated description in the first-row immutable plan preview.

## [0.0.15] - 2026-08-25

### Added

- Add streaming distinct-value discovery for mapped status, type, priority, urgency, impact, user, group, entity, location, and category fields.
- Add persistent value correspondence with explicit ignore decisions and normalized-exact GLPI reference suggestions requiring user confirmation.
- Resolve users by login, name variants, or exact email and resolve tree dropdowns by name or complete name.
- Add a read-only first-row immutable `MigrationPlan` preview with warnings and validation errors and no GLPI business-object writes.

### Changed

- Extend the resumable workflow with a `values_configured` state and contextual navigation.
- Limit each distinct-value set to 200 entries and block progression when the limit requires a refined strategy.

## [0.0.14] - 2026-08-25

### Added

- Add an explicit active CSV source per migration profile and automatically adopt the newest existing revision during upgrade.
- Add a per-profile CSV revision manager with preview, activation, protected deletion, file metadata, and active-source status.
- Add a persistent positional CSV-to-GLPI mapping screen backed by a stable functional target registry.
- Add resumable workflow states and contextual next actions across profile, preview, mapping, and dashboard screens.

### Changed

- Show active sources separately from the total number of retained CSV revisions.
- Preserve a mapping when selecting a revision with the same schema fingerprint and require remapping when the schema changes.
- Store CSV parsing controls per source revision instead of relying only on the profile-wide fallback.

## [0.0.13] - 2026-08-24

### Added

- Add complete British English and French gettext catalogs using the official GLPI plugin translation domain and tooling.

### Changed

- Validate, merge, and compile translation catalogs as a mandatory release-build step.
- Make upload-error labels statically extractable by GLPI's locale extractor.

## [0.0.12] - 2026-08-24

### Fixed

- Clear GLPI application, Symfony, compiled Twig, translation, and menu caches during plugin upgrades so packaged UI changes become visible immediately in production mode.
- Revalidate and carry forward the complete 0.0.7–0.0.11 controller, upload, URL, dashboard, and bounded-preview corrections.

## [0.0.11] - 2026-08-21

### Changed

- Make bounded preview behavior explicit, detect whether additional rows exist, and add profile/upload navigation actions.

## [0.0.10] - 2026-08-21

### Fixed

- Use GLPI 11's namespaced error handler so upload failures remain recoverable and diagnostically useful.
- Clear the consumed `$_FILES` entry after `move_uploaded_file()`, following GLPI/DataInjection request-lifecycle handling and preventing Symfony debug-profiler access to a stale temporary path.

## [0.0.9] - 2026-08-21

### Fixed

- Do not catch GLPI 11 success redirects as upload failures.
- Report PHP upload-limit and transfer errors explicitly, display the effective limit, and clean up stored files after failed preparation.

## [0.0.8] - 2026-08-21

### Fixed

- Build canonical GLPI 11 plugin URLs through a scope-independent helper using the documented `/plugins/ticketmigration` public path.

## [0.0.7] - 2026-08-21

### Fixed

- Import GLPI's global configuration explicitly in the profile and CSV source controllers when dispatched by GLPI 11.

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
