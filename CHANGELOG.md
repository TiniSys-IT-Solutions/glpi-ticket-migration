# Changelog

All notable changes follow [Keep a Changelog](https://keepachangelog.com/) and Semantic Versioning.

## [Unreleased]

## [0.0.41] - 2026-08-26

### Added

- Add a backup-responsibility acknowledgement before final import; the plugin records the GLPI user and timestamp without creating or verifying a dump.
- Add resumable 25-row final-import batches, live progress counters, pause/resume controls, per-row persisted status and a CSV trace export.
- Continue after individual plan or ticket errors and collect every failed or changed row for correction and a later retry run.
- Add a migration-run history screen and links to created GLPI tickets.

### Changed

- Freeze field mappings, value mappings, options and entity-resolution context in every final run so browser-based resume cannot silently change its behavior.
- Disable notifications on each created ticket and present one global completion notification and summary instead of generating thousands of individual notifications.
- Reuse the external-reference ledger across pilot and final runs: unchanged successes are skipped and changed rows are reported without automatic updates.

### Security

- Require import, ticket-creation, profile-visibility and target-entity rights throughout preparation and execution.
- Serialize final-run creation, each batch and each external reference with database advisory locks, and neutralize spreadsheet formulas in exported traces.

## [0.0.40] - 2026-08-26

### Fixed

- Stop catching GLPI 11's successful `RedirectException` as a pilot-creation failure, which previously displayed contradictory success and error messages after one valid POST.
- Initialize `_contracts_id` to `0` in the GLPI ticket input to avoid the core `Ticket::prepareInputForAdd()` undefined-key warning when default contracts are intentionally skipped.

### Added

- Show saved and ready-to-save visual states on explicit location/entity association rows, with an association counter on the collapsible section.
- Add a direct **Field mapping** button to an existing migration profile with an active CSV source.

## [0.0.39] - 2026-08-26

### Fixed

- Populate GLPI 11's native `glpi_tickets.externalid` field from the immutable plan external reference while retaining the plugin-scoped idempotency ledger.
- Prevent concurrent pilot submissions with GLPI's database advisory-lock mechanism and a second ledger check inside the lock.
- Disable the pilot button immediately after confirmation without dropping the submitted action parameter.
- Report a concurrent pilot request as informational instead of displaying a false ticket-creation failure.

## [0.0.38] - 2026-08-26

### Added

- Enable guarded creation of one real GLPI pilot ticket from the exact immutable plan currently being previewed.
- Persist pilot runs, row diagnostics, canonical row hashes, created ticket IDs, and external references transactionally.
- Reuse the external-reference ledger so an imported pilot row is idempotent and will be skipped by the final import.
- Show the created pilot ticket directly in the plan preview and prevent repeat creation.

### Changed

- Normalize workflow footers to a consistent order and visual language: back and secondary navigation, save actions, then the green forward or execution action.
- Execute pilot tickets through GLPI 11's internal `Ticket::add()` lifecycle with per-object notifications, business rules, and automatic assignment disabled for deterministic historical reconstruction.

### Security

- Require the dedicated import right, native GLPI ticket creation right, target-entity access, CSRF protection, and an executable error-free plan before pilot creation.

## [0.0.37] - 2026-08-26

### Fixed

- Classify plan notices by severity instead of displaying successful resolutions as warnings.
- Treat an exact location-name match with the project's default entity as the normal profile fallback rather than a child-entity inference.
- Report the migration profile default entity explicitly when no requester or location association determines a child entity.

### Changed

- Present blocking errors and genuine warnings as alerts, deterministic decisions as information, and successful mapping or fallback rules as validations with distinct GLPI colors and icons.

## [0.0.36] - 2026-08-26

### Added

- Group value-mapping categories into compact expandable blocks with processed/total progress counters.
- Display the resulting ticket title prominently in the row-by-row migration-plan preview.

### Changed

- Present location/entity associations as a safeguard for tickets without a usable requester and show the project default entity explicitly when no association is selected.

### Fixed

- Prevent GLPI's root entity ID `0` from looking like an explicitly selected location/entity association when the profile association is actually empty.

## [0.0.35] - 2026-08-26

### Fixed

- Resolve requester authorizations through GLPI's official `Profile_User::getUserEntities()` mechanism instead of reading raw entity IDs without preference semantics.
- Give a requester's valid preferred or unique Habilitation precedence over native, mapped, or inferred location entities.
- Report requester/location entity conflicts instead of silently selecting the location hierarchy match.

### Added

- Add explicit per-profile GLPI location-to-entity associations for legacy or globally scoped locations.
- Show the native GLPI location entity scope and optional migration association on the value-correspondence screen, while clarifying that GLPI 11 does not expose this scope as a standard editable Location field.
- Display requester authorization as a distinct entity origin in the row-by-row plan preview.

## [0.0.34] - 2026-08-26

### Added

- Add GLPI-native multi-selection actions to archive, restore, clone configuration, and safely delete execution-free migration projects.
- Add an archived-project view and make archived projects read-only while preserving their complete audit data.
- Add storage reporting and explicit cleanup for expired, inactive CSV revisions that are not referenced by a run.
- Preserve cloned field and value mappings without duplicating source CSV payloads, and reuse them when a structurally matching CSV is uploaded.

### Changed

- Start a 30-day retention period on the preceding source revision only when a replacement becomes active; active sources never expire.
- Reject permanent project deletion once a run or external reference exists.

## [0.0.33] - 2026-08-25

### Added

- Add streaming previous/next navigation across CSV rows in the immutable-plan preview.
- Add a prominent resolved-information summary for requesters, technicians, location, entity, source values, and entity-resolution origin.

## [0.0.32] - 2026-08-25

### Fixed

- Prevent a globally scoped GLPI location (entity `0`) from overriding a migration profile's non-root default entity.

### Added

- Infer a child entity only from one exact normalized name match between the resolved location hierarchy and the profile entity subtree.
- Distinguish direct location ownership from hierarchy-name inference in immutable-plan warnings.

## [0.0.31] - 2026-08-25

### Added

- Add a permanent configuration-dashboard warning requiring a verified, restorable GLPI backup before pilot or final import.
- Document a mandatory, server-enforced final-import backup acknowledgement with GLPI user, timestamp, and optional backup reference.

### Security

- Keep backup execution outside the plugin so database credentials and platform-specific dump privileges are never introduced into GLPI.

## [0.0.30] - 2026-08-25

### Added

- Add a visible GLPI-native default-entity selector to migration-profile creation and editing.
- Add strict French and ISO date normalization to GLPI's `Y-m-d H:i:s` format for opening, resolution, and closing dates.
- Add deterministic entity resolution with precedence: explicit mapping, resolved location entity, unique requester entity, then profile default.
- Add plan warnings for inferred location/requester entities and ambiguous requester-entity fallback.

### Changed

- Reject invalid non-empty dates in the immutable plan instead of passing locale-formatted values to the future executor.
- Preserve original date strings in structured historical metadata while using normalized values for GLPI fields.

## [0.0.29] - 2026-08-25

### Fixed

- Always persist automatic and manual actor associations even when unresolved actors are configured for omission in the same submission.
- Apply the omission policy only when a source actor has no saved resolution during migration-plan construction.
- Distinguish omitted actors from remaining decisions in per-category progress statistics.

### Changed

- Rename and clarify the high-cardinality actor option as **Keep resolved associations and omit only unresolved actors**.

## [0.0.28] - 2026-08-25

### Changed

- Highlight complete value decisions with a green row, border, validated control, check badge, and explicit saved/ready state.
- Hide the no-exact-match warning once a valid manual reference has been selected.
- Refresh completion feedback immediately when either the resolution mode or native GLPI selector changes.

## [0.0.27] - 2026-08-25

### Fixed

- Search requesters across all active entities visible to the operator, including accounts without assigned rights.
- Preserve GLPI's official `own_ticket` filter only for assigned-technician searches.
- Format every manual user-search result consistently as `Full name — login (#ID)`.
- Proxy the native GLPI user dropdown result through a permission-protected plugin endpoint without replacing GLPI's filtering or IDOR validation.

## [0.0.26] - 2026-08-25

### Fixed

- Replace the malformed PCRE reference decoder with a deterministic parser for `ref:<itemtype>:<id>` selections.
- Validate itemtype syntax and positive numeric IDs before instantiating and permission-checking GLPI references.
- Add regression coverage for user and namespaced itemtype reference selections.
- Add regression coverage for requester and technician GLPI dropdown scopes.

## [0.0.25] - 2026-08-25

### Added

- Add saved manual-association counts to the global statistics, per-category cards, and last saved analysis summary.

### Changed

- Count a persisted manual choice or explicit ignore as completed work instead of leaving it in the remaining-decision total.

## [0.0.24] - 2026-08-25

### Fixed

- Replace fragile parallel POST arrays with one cryptographically keyed resolution field per source decision.
- Reconstruct source values, target keys, and unique automatic matches server-side before draft persistence.
- Prevent blank and inactive manual controls from consuming PHP `max_input_vars` during large draft submissions.
- Validate final completeness against the transactionally merged repository state, including earlier drafts.
- Use GLPI's complete requester-user search, including visible accounts without assigned rights, while keeping the official `own_ticket` technician filter for assignees.

## [0.0.23] - 2026-08-25

### Added

- Add partial progress saving to the value-correspondence screen without advancing the migration workflow.
- Add a distinct final validation action that still requires every current source value to be resolved or ignored.

### Changed

- Merge progress decisions transactionally with previously saved work instead of replacing the complete profile mapping.
- Count only genuinely unresolved values as remaining after a draft is resumed.
- Preserve partial correspondence progress and the latest analysis summary across sessions.

## [0.0.22] - 2026-08-25

### Fixed

- Disambiguate homonymous user suggestions by displaying the GLPI login and numeric ID after the full name.

## [0.0.21] - 2026-08-25

### Fixed

- Split mixed comma-separated actor lists containing e-mail addresses, logins, or display labels.
- Ignore empty actor components produced by trailing commas.
- Preserve comma-formatted person names when no e-mail marker establishes that the value is a list.

## [0.0.20] - 2026-08-25

### Fixed

- Automatically split comma-separated e-mail actor lists into independent correspondence values and independent migration-plan actors.
- Preserve person names formatted as `Last name, First name` when automatic separator detection is used.

### Changed

- Apply contextual comma detection through the shared actor splitter used by both distinct-value analysis and immutable plan construction.

## [0.0.19] - 2026-08-25

### Added

- Add a configurable fallback for empty mapped titles using the first 3–30 words of the main description.
- Fall back to `Ticket <external identifier>` when both the mapped title and main description are empty.
- Report generated titles as migration-plan warnings for auditability.

## [0.0.18] - 2026-08-25

### Added

- Add overall and per-category value-analysis statistics, including perfect automatic matches and decisions remaining.
- Retain the last saved analysis summary and source filename so a revised CSV can be compared with the previous result.
- Show the number of distinct actors detected after multi-value separation.

### Changed

- Hide unique exact reference matches from the manual worklist while submitting them as prepared decisions for explicit form validation.
- Reveal the full GLPI selector only when manual selection is chosen and synchronize a manual GLPI choice with its resolution mode.
- Move actor separators into a dedicated mapping-table column and display the selector only for requester, technician, and actor-group targets.
- Move the historical-description explanation into the description-consolidation section.

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
