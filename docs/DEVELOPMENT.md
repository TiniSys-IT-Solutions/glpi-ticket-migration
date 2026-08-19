# Development

Use PHP 8.2+, PSR-12, strict domain boundaries, translated English source labels, and GLPI's DB/API abstractions. Validate behavior against the pinned local GLPI 11.0.8 source before relying on any internal API.

Unit tests cover pure readers, mappings, transformations, planning, hashing, and security policies. Integration tests run inside a disposable GLPI installation and cover install/uninstall, rights/entities, dry-run non-mutation, object lifecycle, notifications, and idempotence.

Versioning follows Semantic Versioning and Keep a Changelog. Development starts at `0.1.0-dev`; tagged prereleases use `0.1.0-alpha.N`, then `beta.N`, `rc.N`, and finally `0.1.0`.
