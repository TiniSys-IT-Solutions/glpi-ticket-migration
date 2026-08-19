# Security model

- Every front/AJAX action checks a dedicated GLPI right, entity visibility, and CSRF token before mutation.
- Uploaded files use a random internal name under a GLPI-approved plugin data directory; source name, SHA-256, size, owner, and retention metadata are persisted.
- CSV is streamed. Full files and large row collections never enter `$_SESSION`.
- Remote attachments accept HTTP(S) only, resolve and reject loopback/link-local/private destinations by default, revalidate redirects, enforce allowlists, timeouts, byte limits, MIME checks, and sanitized names.
- Source text is escaped/sanitized for GLPI-compatible HTML. Error CSV exports neutralize spreadsheet-formula prefixes.
- Imports pass `_disablenotif` to every GLPI object creation/update that supports it. The plugin never disables GLPI notifications globally.
- Logs identify run, row, external ID, created IDs, and diagnostic codes without dumping ticket bodies, secrets, or remote credentials.

Historical metadata restoration is disabled until a version/schema-guarded implementation is integration-tested. Any future direct core-data correction must be centralized, transactional, limited to objects created by the current run, auditable, and optional.
