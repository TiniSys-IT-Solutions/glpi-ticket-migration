# Mapping engine

Mappings address source columns by zero-based position plus their display header. Supported target strategies are direct field, constant, value map, resolver lookup, safe template, transform chain, structured-description contribution, and ignore.

Templates accept fixed text and explicit column placeholders only. No `eval`, PHP, or JavaScript is allowed. Transformations are allowlisted and typed. Conditions are data-only expressions (`equals`, `not_equals`, `contains`, `starts_with`, `ends_with`, controlled `regex`, `empty`, `not_empty`).

The target registry exposes stable functional concepts, not SQL columns. Each target declares its value type, cardinality, creation phase, resolver, compatible transformations, and dependencies. `Entity from resolved Location` is modeled as a dependency rather than a second source mapping.

The first mapping UI persists one explicit decision for every positional source column: direct functional target or ignore. External ticket identifier and title are mandatory before the profile reaches `mapping_configured`. A target may only be selected once in this initial direct-mapping stage. Value maps, resolvers, transforms, structured descriptions, and repeated timeline contributions build on these persisted decisions in subsequent stages.

Controlled fields then use streaming distinct-value discovery. Status, type, priority, urgency, and impact are mapped to GLPI 11 constants. Users, groups, entities, locations, and ITIL categories use exact or normalized-exact lookup against GLPI objects; matches remain suggestions until explicitly saved. Unknown values must be ignored explicitly or remain blocking. Each field is bounded to 200 distinct values so an unsuitable high-cardinality mapping cannot generate an unbounded form.

Once value correspondence is complete, the same mapping data builds a read-only immutable plan for the first source row. This preview performs no GLPI business-object write and is the foundation shared by the upcoming pilot import and full dry run.

Structured description values are escaped before safe markup is generated. The main description column is identified explicitly and rendered in its own section. A profile may include mapped fields, unmapped fields, or both in a historical-data section, exclude sensitive or technical columns individually, and position metadata before or after the main description. Empty fields are always omitted and an HTML separator keeps both sections readable. A profile keeps all decisions so later delta files are interpreted identically.
