# Development

Use PHP 8.2+, PSR-12, strict domain boundaries, translated English source labels, and GLPI's DB/API abstractions. Validate behavior against the pinned local GLPI 11.0.8 source before relying on any internal API.

Unit tests cover pure readers, mappings, transformations, planning, hashing, and security policies. Integration tests run inside a disposable GLPI installation and cover install/uninstall, rights/entities, dry-run non-mutation, object lifecycle, notifications, and idempotence.

Development releases increment `0.0.x`; the first stabilized pre-production release will be `0.1.0`. A release is valid only when tests, build, and archive verification succeed before the commit and annotated tag are created.

`tests/fixtures/csv/generic-helpdesk-acceptance.csv` is synthetic and contains representative multiline text, accents, mixed actor forms, dates and attachment URLs. It must remain vendor-neutral and contain no customer data.
