# Security model

- Every front/AJAX action checks a dedicated GLPI right, entity visibility, and CSRF token before mutation.
- Plugin rights are bootstrapped once for profiles that already hold GLPI configuration-update permission. Later profile-right changes are preserved. GLPI administrators retain a page-level recovery path through their native configuration-update permission; all other profiles require the dedicated plugin rights.
- Uploaded files use a random internal name under a GLPI-approved plugin data directory; extension, MIME, size and upload provenance are validated, while source name, SHA-256, owner and retention metadata are persisted.
- HTTP uploads are validated with `is_uploaded_file()`, transferred with `move_uploaded_file()`, and removed from `$_FILES` immediately after consumption so GLPI/Symfony cannot reuse a stale temporary path during request shutdown.
- CSV is streamed. Full files and large row collections never enter `$_SESSION`.
- Remote attachments accept HTTP(S) only, resolve and reject loopback/link-local/private destinations by default, revalidate redirects, enforce allowlists, timeouts, byte limits, MIME checks, and sanitized names.
- Source text is escaped/sanitized for GLPI-compatible HTML. Structured descriptions escape every source label and value before adding plugin-owned headings, line breaks, and separators; profiles can exclude sensitive columns. Error CSV exports neutralize spreadsheet-formula prefixes.
- Manual reference mapping uses GLPI's native AJAX dropdowns and validates the selected object again with `canViewItem()` before persistence. User records are never created during resolution.
- Imports pass `_disablenotif` to every GLPI object creation/update that supports it. The plugin never disables GLPI notifications globally.
- Logs identify run, row, external ID, created IDs, and diagnostic codes without dumping ticket bodies, secrets, or remote credentials.

Historical metadata restoration is disabled until a version/schema-guarded implementation is integration-tested. Any future direct core-data correction must be centralized, transactional, limited to objects created by the current run, auditable, and optional.
