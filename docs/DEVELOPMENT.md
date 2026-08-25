# Development

Use PHP 8.2+, PSR-12, strict domain boundaries, translated English source labels, and GLPI's DB/API abstractions. Validate behavior against the pinned local GLPI 11.0.8 source before relying on any internal API.

Unit tests cover pure readers, mappings, transformations, planning, hashing, and security policies. Integration tests run inside a disposable GLPI installation and cover install/uninstall, rights/entities, dry-run non-mutation, object lifecycle, notifications, and idempotence.

GLPI production mode disables Twig template auto-reload. The installer therefore clears GLPI caches after a successful install or upgrade so changed plugin templates and menu definitions are compiled again on the next request.

Development releases increment `0.0.x`; the first stabilized pre-production release will be `0.1.0`. A release is valid only when tests, build, and archive verification succeed before the commit and annotated tag are created.

Plugin strings are authored in British English and always use the `ticketmigration` gettext domain. Locale maintenance follows GLPI's official plugin workflow through `glpi-project/tools`: `vendor/bin/extract-locales`, `msgmerge`, and `msgfmt`. Install GNU gettext locally before building; the release script rejects missing, invalid, or untranslated FR/EN catalogs.

`tests/fixtures/csv/generic-helpdesk-acceptance.csv` is synthetic and contains representative multiline text, accents, mixed actor forms, dates and attachment URLs. It must remain vendor-neutral and contain no customer data.
