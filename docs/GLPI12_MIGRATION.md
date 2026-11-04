# GLPI 12 migration

The `glpi-v12` branch prepares 0.2.0 for GLPI **12.0.x** (`>=12.0.0`, `<12.1.0`) and PHP **8.3+**.
The GLPI 11 release remains on `main`; no GLPI 12 release tag is published before review and merge.

## Native adaptations

- Match the typed model and relation properties inherited from GLPI 12.
- Use PHP class names for native item types instead of legacy `getType()` calls.
- Remove the retired `CSRF_COMPLIANT` hook. GLPI 12 enforces request-origin checks through its native kernel listener; no security check is bypassed.
- Keep GLPI rights, entity restrictions, internal business APIs and installer schema safeguards.
- Synchronize PHP constraints, plugin metadata, Composer lock data and the PHP 8.3/8.4 CI matrix.

## Validation

A disposable GLPI 12.0.1 installation with MariaDB 11.4 was used locally. The plugin installs, activates, initializes and loads all its declarations against native GLPI parents.
The isolated regression suites and release packaging are checked separately.

The reproducible native runner requires a `.tinisys-disposable-test` marker at the GLPI root:

```bash
GLPI_TEST_ROOT=/absolute/path/to/disposable/glpi php tests/Integration/glpi12.php
```

Install and activate the plugin first. Native jobs use production Composer dependencies (`--no-dev`) to avoid loading plugin development copies of Symfony into the GLPI kernel.
`.github/workflows/glpi12.yml` repeats installation, activation, model loading, deactivation and uninstall on PHP 8.3 and 8.4.

## Remaining review

Validate a full pilot and bulk import on a representative synthetic CSV in the target GLPI 12 deployment.
Review the rendered screens, keyboard interactions, translations and entity/profile combinations on the target deployment before merging or installing in production.
The automated native checks cover initialization and lifecycle; they do not replace a complete browser acceptance test or certify all third-party integrations.

## Reviewed references

- [Tag migration](https://github.com/pluginsGLPI/tag/commit/d7f547592c5e6627dc0c42fb573c5e2f448c3ff5)
- [Fields migration](https://github.com/pluginsGLPI/fields/commit/8a679ffd01067b38c6ce98367fa047e778da6493)
- [Addressing migration](https://github.com/pluginsGLPI/addressing/commit/ba3a3ba1e65cfb3ee7f577e5b9f89e1798b0d563)
- [GLPI 12.0.1 source](https://github.com/glpi-project/glpi/tree/12.0.1)
