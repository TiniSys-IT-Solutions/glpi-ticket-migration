#!/usr/bin/env bash
set -euo pipefail

PLUGIN_KEY="ticketmigration"
REPOSITORY_NAME="glpi-ticket-migration"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TAG_NAME="${1:-v0.0.1}"
VERSION="${TAG_NAME#v}"
DIST_DIR="${ROOT_DIR}/dist"
BUILD_DIR="${DIST_DIR}/build"
PACKAGE_DIR="${BUILD_DIR}/${PLUGIN_KEY}"
ARCHIVE="${DIST_DIR}/${REPOSITORY_NAME}-${VERSION}.zip"

cd "${ROOT_DIR}"
PLUGIN_VERSION="$(sed -n "s/^define('PLUGIN_TICKETMIGRATION_VERSION', '\([^']*\)');/\1/p" setup.php)"
[[ -n "${PLUGIN_VERSION}" ]] || { echo "Unable to read plugin version" >&2; exit 1; }
[[ "${VERSION}" == "${PLUGIN_VERSION}" ]] || { echo "Version mismatch: ${VERSION} != ${PLUGIN_VERSION}" >&2; exit 1; }

for command in composer php rsync python3; do
  command -v "${command}" >/dev/null 2>&1 || { echo "Missing command: ${command}" >&2; exit 1; }
done

composer validate --strict --no-check-publish
php vendor/bin/phpunit

rm -rf "${BUILD_DIR}"
mkdir -p "${PACKAGE_DIR}"
rm -f "${ARCHIVE}"

rsync -a ./ "${PACKAGE_DIR}/" \
  --exclude '.git/' --exclude '.github/' --exclude 'dist/' \
  --exclude 'vendor/' --exclude 'tests/' --exclude 'scripts/' \
  --exclude '.gitignore' --exclude '.phpunit.cache/' \
  --exclude '.phpunit.result.cache' --exclude 'AGENTS.md' \
  --exclude 'phpunit.xml'

(
  cd "${PACKAGE_DIR}"
  composer install --no-dev --no-interaction --prefer-dist \
    --optimize-autoloader --classmap-authoritative
)
rm -f "${PACKAGE_DIR}/composer.lock"

find "${PACKAGE_DIR}" -type f -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null

ARCHIVE="${ARCHIVE}" BUILD_DIR="${BUILD_DIR}" PLUGIN_KEY="${PLUGIN_KEY}" python3 - <<'PY'
import os
import pathlib
import zipfile

archive = pathlib.Path(os.environ['ARCHIVE'])
build_dir = pathlib.Path(os.environ['BUILD_DIR'])
plugin_dir = build_dir / os.environ['PLUGIN_KEY']
with zipfile.ZipFile(archive, 'w', zipfile.ZIP_DEFLATED) as output:
    for path in plugin_dir.rglob('*'):
        if path.is_file():
            output.write(path, path.relative_to(build_dir))
with zipfile.ZipFile(archive) as package:
    names = set(package.namelist())
    required = {'ticketmigration/setup.php', 'ticketmigration/hook.php',
                'ticketmigration/composer.json', 'ticketmigration/vendor/autoload.php'}
    missing = required - names
    if missing:
        raise SystemExit(f'Missing required entries: {sorted(missing)}')
    if any(not name.startswith('ticketmigration/') for name in names):
        raise SystemExit('Invalid archive root')
    if any('/tests/' in name or '/.git/' in name or '/dist/' in name for name in names):
        raise SystemExit('Development files found in archive')
print(f'Verified {archive}: {len(names)} entries')
PY

