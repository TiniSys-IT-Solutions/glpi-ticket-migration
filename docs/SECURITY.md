# Security model

- Every front/AJAX action checks a dedicated GLPI right, entity visibility, and CSRF token before mutation.
- Location-based entity inference is restricted to the migration profile's authorized entity subtree and accepts only one exact normalized hierarchy-name match; it never selects an ambiguous or out-of-scope entity.
- Requester entity resolution accepts only one unique direct `Profile_User` entity inside the migration project scope. Ambiguous requesters fall through to an explicitly validated profile-level location/entity association, then to the project default; preferred-user and GLPI-root entities cannot escape that boundary.
- Bulk profile actions re-check item visibility and the dedicated CREATE, UPDATE, or DELETE right for every selected project. Permanent project deletion is refused when any run or external reference exists.
- Plugin rights are bootstrapped once for profiles that already hold GLPI configuration-update permission. Later profile-right changes are preserved. GLPI administrators retain a page-level recovery path through their native configuration-update permission; all other profiles require the dedicated plugin rights.
- Uploaded files use a random internal name under a GLPI-approved plugin data directory; extension, MIME, size and upload provenance are validated, while source name, SHA-256, owner and retention metadata are persisted.
- HTTP uploads are validated with `is_uploaded_file()`, transferred with `move_uploaded_file()`, and removed from `$_FILES` immediately after consumption so GLPI/Symfony cannot reuse a stale temporary path during request shutdown.
- CSV is streamed. Full files and large row collections never enter `$_SESSION`.
- Remote attachments accept HTTP(S) only, resolve and reject loopback/link-local/private destinations by default, revalidate redirects, enforce allowlists, timeouts, byte limits, MIME checks, and sanitized names.
- Source text is escaped/sanitized for GLPI-compatible HTML. Structured descriptions escape every source label and value before adding plugin-owned headings, line breaks, and separators; profiles can exclude sensitive columns. Error CSV exports neutralize spreadsheet-formula prefixes.
- Manual reference mapping uses GLPI's native AJAX dropdowns and validates the selected object again with `canViewItem()` before persistence. User records are never created during resolution.
- Value-form decisions use server-recomputed SHA-256 form keys bound to the target and source value. The server rejects unknown keys and reconstructs trusted mapping context instead of accepting client-submitted target metadata.
- Submitted reference tokens are parsed as exactly three components, restricted to a valid itemtype name and positive integer ID, matched against the target registry, then loaded and permission-checked before persistence.
- The plugin user-search endpoint delegates filtering and IDOR validation to `Dropdown::getDropdownUsers()`, requires plugin profile access, enriches only authorized result labels, and rechecks selected objects during persistence.
- Imports pass `_disablenotif` to every GLPI object creation/update that supports it. Pilot ticket input also uses GLPI's supported `_skip_rules` and `_skip_auto_assign` controls so historical plans are not altered by current business rules or entity auto-assignment. The plugin never disables GLPI notifications or rules globally.
- Pilot execution requires the dedicated plugin import right, GLPI's native ticket CREATE right, visibility of the migration profile, access to the resolved target entity, a valid CSRF token, and an error-free immutable plan. Ticket creation and the plugin run/external-reference ledger are committed together; failure registers no idempotency reference. GLPI's scoped database advisory lock serializes concurrent attempts for the same profile/external-reference pair and is always released in a `finally` block.
- Logs identify run, row, external ID, created IDs, and diagnostic codes without dumping ticket bodies, secrets, or remote credentials.

Historical metadata restoration is disabled until a version/schema-guarded implementation is integration-tested. Any future direct core-data correction must be centralized, transactional, limited to objects created by the current run, auditable, and optional.

## Backup gate

The configuration dashboard permanently recommends a recent, verified, restorable backup of the complete GLPI environment, or at minimum the complete MySQL database. GLPI file storage must also be covered whenever documents or attachments are in scope. The plugin does not execute database dumps or store database credentials.

The final import UI and server-side execution service reject execution until the operator explicitly accepts responsibility for backup preparation. The plugin neither proves that a dump exists nor claims that it is restorable. The acknowledgement is immutable run metadata containing the confirming GLPI user and timestamp; a client-side checkbox alone is not considered sufficient authorization.

Final runs freeze mappings, options, entity context, source identifier, and source SHA-256 at creation. Short batches and per-row records make browser interruption recoverable. A persisted expiring lease serializes batch workers without relying on a PHP connection-scoped database lock. A retry is a new frozen run: the permanent ledger skips successes and prevents corrected work from duplicating them.

CSV retention cleanup never targets the active revision or a hash referenced by a run. It removes only an expired inactive payload from protected storage and keeps soft-deleted metadata. Project cloning copies configuration and mapping decisions but deliberately does not duplicate the potentially large or sensitive CSV payload.
