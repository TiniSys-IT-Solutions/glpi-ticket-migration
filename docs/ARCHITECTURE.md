# Architecture

## Boundaries

```mermaid
flowchart TD
  UI[GLPI controllers and Twig] --> App[Application services]
  App --> Reader[SourceReaderInterface]
  Reader --> CSV[CsvReader]
  App --> Mapping[Mapping Engine]
  Mapping --> Resolver[Resolver Engine]
  Mapping --> Plan[MigrationPlan]
  Plan --> Dry[Dry Run reporter]
  Plan --> Exec[GLPI Executor]
  Exec --> Core[GLPI 11 objects]
  App --> Repo[(Plugin repositories)]
  Repo --> DB[(Plugin tables)]
```

The domain never depends on HTTP or session state. The same plan-building path feeds dry run and execution, and both pilot and mass execution call the same `GlpiTicketExecutor` backed by GLPI's internal `Ticket::add()` mechanism. Only the executor writes GLPI business objects. Final execution follows the GLPI DataInjection convention of short AJAX batches and an in-page progress bar, while offsets, leases, counters, row outcomes and the latest batch diagnostic remain durable database state rather than browser or PHP-session state.

## Components

- **Source**: streaming readers emit positional `SourceRow` values and `SourceColumn` identities (`index:name`), preserving duplicate headers.
- **Mapping**: field, constant, value-map, resolver, template, transform, structured-description, and ignore strategies produce typed target values.
- **Resolvers**: exact, normalized exact, then fuzzy suggestions. Suggestions are never automatically applied.
- **Plan**: immutable ticket aggregate containing actors, timeline, documents, relations, external reference, warnings, and errors.
- **Plan context**: permission-checked GLPI locations, explicit profile location/entity bridges and official `Profile_User` authorization metadata are collected outside the domain builder. The query-free domain applies one unique requester entity first, then an explicit location bridge, then the project default. Preferred-user entities, native location scope and hierarchy-name inference are deliberately excluded from automatic destination decisions.
- **Execution**: converts the immutable plan into GLPI's official `Ticket::add()` input, creates GLPI objects in a controlled lifecycle with `_disablenotif`, `_skip_rules`, and `_skip_auto_assign`, and isolates a failed source row from subsequent rows. Pilot and final execution share this executor.
- **Persistence**: profiles, mappings, runs, row states, and external references remain in plugin-prefixed tables.
- **Source storage**: random internal names under `GLPI_PLUGIN_DOC_DIR/ticketmigration/sources`; metadata and retention state live in `sourcefiles`.

CSV rows are never duplicated into SQL tables. Field mappings store one compact row per source column, value mappings store one row per distinct controlled source value, and location/entity bridges store one row per resolved GLPI location requiring an override. Run items retain status, hashes, warnings, errors, and created-object identifiers rather than complete source payloads. External references and completed run history are permanent audit data.

Final runs snapshot field/value mappings, options, and the permission-checked entity-resolution context. A browser-driven worker handles bounded batches and commits a terminal state for each row. Recovering an interrupted run reuses that snapshot; applying corrected configuration creates a new run whose ledger classification skips earlier successes. This separates resumability from configuration changes and keeps each execution reproducible.

The progress screen calls a dedicated JSON batch endpoint and updates only its counters and recent trace. A database-persisted batch token provides compare-and-set ownership and expires after an abandoned worker, avoiding connection-scoped advisory locks that can survive in persistent PHP database pools. Each request handles at most ten rows; the token is released in `finally`, while every completed row remains independently committed.

Each uploaded source receives a schema fingerprint over CSV controls and ordered positional columns. This lets a profile reject structurally incompatible delta files without relying on unique header names.

A profile explicitly references one active source revision through `sourcefiles_id`. Revisions remain auditable and selectable; choosing a structurally identical revision preserves positional mappings, while a different schema returns the workflow to mapping. Parsing controls are stored with each revision so preview and mapping always interpret that exact file consistently.

When a new revision becomes active, the previous revision receives a 30-day retention deadline. Cleanup is limited to expired, inactive payloads that are not referenced by a migration run; their small metadata record and hash remain soft-deleted for traceability. The active source and every executed source are excluded. Archived projects are read-only and retain profiles, mappings, sources, runs, and external references.

## Persistence

Frequently filtered fields are normal columns and indexed. Extensible mapping/options payloads use JSON. `profiles_id + external_id` is unique and is the technical idempotency key. The same value is also passed to GLPI 11's native `Ticket.externalid` field so it remains visible and searchable on the created ticket; plugin-scoped idempotency does not rely on that global core field. A successful pilot writes the same external-reference ledger used by final execution, so that row cannot create a second ticket later. Per-reference database advisory locking and an in-lock ledger recheck protect concurrent requests. No core table schema is changed. Uninstalling the plugin drops plugin data only; migrated tickets remain GLPI-owned.

Configuration workflow state is persisted on the profile (`profile_created`, `source_selected`, `mapping_configured`, `values_configured`). It drives setup navigation but never substitutes for validation; pilot and final execution state belongs to run history.

Configuration workflow and execution lifecycle are deliberately separate. `ProfileOperationalState` derives the user-facing pilot/final status from immutable run records, while `workflow_step` continues to describe only source and mapping readiness. This avoids conflicting state updates when several pilots or retry runs exist.

## Dependency rule

Data Injection is an architectural reference only. Ticket Migration neither loads its classes nor reads its tables. Source reader, mapping, plan, execution, and persistence contracts belong to this plugin.
