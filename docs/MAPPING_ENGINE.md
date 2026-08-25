# Mapping engine

Mappings address source columns by zero-based position plus their display header. Supported target strategies are direct field, constant, value map, resolver lookup, safe template, transform chain, structured-description contribution, and ignore.

Templates accept fixed text and explicit column placeholders only. No `eval`, PHP, or JavaScript is allowed. Transformations are allowlisted and typed. Conditions are data-only expressions (`equals`, `not_equals`, `contains`, `starts_with`, `ends_with`, controlled `regex`, `empty`, `not_empty`).

The target registry exposes stable functional concepts, not SQL columns. Each target declares its value type, cardinality, creation phase, resolver, compatible transformations, and dependencies. `Entity from resolved Location` is modeled as a dependency rather than a second source mapping.

The first mapping UI persists one explicit decision for every positional source column: direct functional target or ignore. External ticket identifier and title are mandatory before the profile reaches `mapping_configured`. A target may only be selected once in this initial direct-mapping stage. Value maps, resolvers, transforms, structured descriptions, and repeated timeline contributions build on these persisted decisions in subsequent stages.

Structured description values are escaped before safe markup is generated. Empty fields are omitted by default and unmapped-field inclusion is opt-in. A profile keeps all decisions so later delta files are interpreted identically.
