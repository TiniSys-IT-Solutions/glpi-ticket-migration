# Development

Use PHP 8.3+, PSR-12, strict domain boundaries, translated English source labels, and GLPI's DB/API abstractions. Validate behavior against the pinned local GLPI 12.0.8 source before relying on any internal API.

Unit tests cover pure readers, mappings, transformations, planning, hashing, and security policies. Execution regression tests use isolated GLPI and database test doubles to inject ledger, progress and lifecycle failures and verify rollback and replay. These are not live GLPI integration tests. Install/upgrade, native object lifecycle, notifications, rights and concurrent MySQL workers still require a disposable GLPI 12.0.8 installation.

GLPI production mode disables Twig template auto-reload. The installer therefore clears GLPI caches after a successful install or upgrade so changed plugin templates and menu definitions are compiled again on the next request.

Public pre-1.0 releases increment `0.1.x`. A release is valid only when tests,
build and archive verification succeed before the commit and annotated tag are
created. The first clean public baseline is `0.1.1`.

The build replaces `dist/ticketmigration/` and the current version ZIP while preserving earlier archives. The installable artifact is `dist/ticketmigration-VERSION.zip`. Published GitHub releases are unaffected.

Plugin strings are authored in British English and always use the `ticketmigration` gettext domain. Locale maintenance follows GLPI's official plugin workflow through `glpi-project/tools`: `vendor/bin/extract-locales`, `msgmerge`, and `msgfmt`. Install GNU gettext locally before building; the release script rejects missing, invalid, or untranslated FR/EN catalogs.

`tests/fixtures/csv/generic-helpdesk-acceptance.csv` is synthetic and contains representative multiline text, accents, mixed actor forms, dates and attachment URLs. It must remain vendor-neutral and contain no customer data.
