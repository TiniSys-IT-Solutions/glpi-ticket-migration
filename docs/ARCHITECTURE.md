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

The domain never depends on HTTP or session state. The same plan-building path feeds dry run and execution. Only the executor writes GLPI business objects.

## Components

- **Source**: streaming readers emit positional `SourceRow` values and `SourceColumn` identities (`index:name`), preserving duplicate headers.
- **Mapping**: field, constant, value-map, resolver, template, transform, structured-description, and ignore strategies produce typed target values.
- **Resolvers**: exact, normalized exact, then fuzzy suggestions. Suggestions are never automatically applied.
- **Plan**: immutable ticket aggregate containing actors, timeline, documents, relations, external reference, warnings, and errors.
- **Execution**: creates GLPI objects in a controlled lifecycle with `_disablenotif`; it isolates a failed source row from subsequent rows.
- **Persistence**: profiles, mappings, runs, row states, and external references remain in plugin-prefixed tables.

## Persistence

Frequently filtered fields are normal columns and indexed. Extensible mapping/options payloads use JSON. `profiles_id + external_id` is unique and is the technical idempotency key. No core table schema is changed. Uninstalling the plugin drops plugin data only; migrated tickets remain GLPI-owned.

## Dependency rule

Data Injection is an architectural reference only. Ticket Migration neither loads its classes nor reads its tables. Source reader, mapping, plan, execution, and persistence contracts belong to this plugin.
