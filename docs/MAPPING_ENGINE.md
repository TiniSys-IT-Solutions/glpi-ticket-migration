# Mapping engine

Mappings address source columns by zero-based position plus their display header. Supported target strategies are direct field, constant, value map, resolver lookup, safe template, transform chain, structured-description contribution, and ignore.

Templates accept fixed text and explicit column placeholders only. No `eval`, PHP, or JavaScript is allowed. Transformations are allowlisted and typed. Conditions are data-only expressions (`equals`, `not_equals`, `contains`, `starts_with`, `ends_with`, controlled `regex`, `empty`, `not_empty`).

The target registry exposes stable functional concepts, not SQL columns. Each target declares its value type, cardinality, creation phase, resolver, compatible transformations, and dependencies. `Entity from resolved Location` is modeled as a dependency rather than a second source mapping.

The first mapping UI persists one explicit decision for every positional source column: direct functional target or ignore. External ticket identifier and title are mandatory before the profile reaches `mapping_configured`. A target may only be selected once in this initial direct-mapping stage. Value maps, resolvers, transforms, structured descriptions, and repeated timeline contributions build on these persisted decisions in subsequent stages.

Controlled fields then use streaming distinct-value discovery. Status, type, priority, urgency, and impact are mapped to GLPI 11 constants. Users, groups, entities, locations, and ITIL categories use exact or normalized-exact lookup against GLPI objects. A unique exact suggestion is preselected, but remains unvalidated until the operator saves the form. The official GLPI AJAX dropdowns provide permission-aware manual search without materializing complete reference tables in the page. Unknown values must be ignored explicitly or remain blocking; the plugin never creates users automatically.

User-reference suggestion labels contain the full name, GLPI login, and numeric object ID. This keeps homonymous accounts distinguishable without changing the persisted reference, which remains the verified `User` itemtype and ID.

Manual requester lookup uses GLPI's native `all` user scope with `with_no_right`, so visible requester accounts without a profile assignment remain selectable. Manual assignee lookup uses GLPI's official `own_ticket` scope and therefore lists technicians eligible to own tickets. Both selections retain entity scoping, IDOR protection, and server-side `canViewItem()` validation.

Unique reference matches are represented as prepared hidden decisions so the operator sees only exceptions while a single form save still validates the complete set. Aggregate and per-target counts are calculated from the current source. A bounded summary of the last saved analysis is stored in profile options for comparison with a later active source; row payloads remain outside profiles and PHP sessions.

Value correspondence supports transactional partial progress saving. A progress save upserts only submitted decisions by profile, target key, and canonical source-value hash, preserving all earlier work and leaving the workflow at `mapping_configured`. Final validation advances to `values_configured` only after the merged repository state covers every non-skipped distinct value.

Large correspondence forms submit one SHA-256-keyed resolution per completed manual decision. Blank controls and inactive GLPI selectors are disabled before submission, while unique automatic matches are rebuilt server-side. The server derives target and source context from the current streamed value sets, merges the submitted decisions, and validates final completeness against repository state. This avoids parallel-array drift and reduces pressure on PHP `max_input_vars`.

Progress statistics distinguish unique automatic reference matches, persisted manual decisions (including explicit ignore), and unresolved values. The same three-way counts are exposed globally, by mapping target, and in the bounded last-analysis summary.

Title remains a required field mapping, but individual rows may contain an empty value. An enabled profile fallback generates `Ticket — <description excerpt>` from a configurable number of words in the raw main description. If both values are empty, it generates `Ticket <external identifier>`. A non-empty mapped title is never modified, and each fallback is recorded as a plan warning.

Requester, assigned-technician, requester-group, and technician-group source fields may contain multiple actors. Their separator is stored with the field mapping and applied identically by distinct-value discovery and `MigrationPlanBuilder`. Automatic mode recognizes semicolons, pipes, and line breaks. It also recognizes comma-separated lists as soon as one non-empty component is a valid e-mail address, allowing mixed e-mail, login, and display-label lists while ignoring trailing empty components. Person names formatted as `Last name, First name` remain intact when no e-mail establishes that the value is a list; unrestricted comma splitting remains an explicit choice. Each controlled field is bounded to 200 distinct values. For actor fields only, an explicit profile policy can omit the whole actor role and allow ticket import to continue without those actors; all omissions are reported as plan warnings.

Once value correspondence is complete, the same mapping data builds a read-only immutable plan for the first source row. This preview performs no GLPI business-object write and is the foundation shared by the upcoming pilot import and full dry run.

Structured description values are escaped before safe markup is generated. The main description column is identified explicitly and rendered in its own section. A profile may include mapped fields, unmapped fields, or both in a historical-data section, exclude sensitive or technical columns individually, and position metadata before or after the main description. Empty fields are always omitted and an HTML separator keeps both sections readable. A profile keeps all decisions so later delta files are interpreted identically.
