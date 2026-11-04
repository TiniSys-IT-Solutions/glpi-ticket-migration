#!/usr/bin/env bash
set -euo pipefail

PLUGIN_KEY="ticketmigration"
ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TAG_NAME="${1:-}"
DIST_DIR="${ROOT_DIR}/dist"
BUILD_DIR="${DIST_DIR}"
PACKAGE_DIR="${BUILD_DIR}/${PLUGIN_KEY}"

cd "${ROOT_DIR}"
PLUGIN_VERSION="$(sed -n "s/^define('PLUGIN_TICKETMIGRATION_VERSION', '\([^']*\)');/\1/p" setup.php)"
[[ -n "${PLUGIN_VERSION}" ]] || { echo "Unable to read plugin version" >&2; exit 1; }
VERSION="${TAG_NAME#v}"
[[ -n "${VERSION}" ]] || VERSION="${PLUGIN_VERSION}"
[[ "${VERSION}" == "${PLUGIN_VERSION}" ]] || { echo "Version mismatch: ${VERSION} != ${PLUGIN_VERSION}" >&2; exit 1; }
ARCHIVE="${DIST_DIR}/${PLUGIN_KEY}-${VERSION}.zip"

for command in composer php rsync python3 xgettext msgmerge msgfmt msgattrib; do
  command -v "${command}" >/dev/null 2>&1 || { echo "Missing command: ${command}" >&2; exit 1; }
done

composer validate --strict --no-check-publish
php vendor/bin/phpunit

rm -rf "${PACKAGE_DIR}" "${DIST_DIR}/build"
vendor/bin/extract-locales
# Catalog timestamps are release metadata, not the time of the local build.
VERSION="${VERSION}" python3 - <<'CATALOG'
import os
import pathlib
import re
release = re.search(r'^## \[' + re.escape(os.environ['VERSION']) + r'\] - (\d{4}-\d{2}-\d{2})', pathlib.Path('CHANGELOG.md').read_text(), re.M)
if release is None:
    raise SystemExit('Release date is missing from CHANGELOG.md')
timestamp = release.group(1) + ' 00:00+0000'
for filename in ('ticketmigration.pot', 'en_GB.po'):
    path = pathlib.Path('locales') / filename
    contents = path.read_text()
    contents = re.sub(r'POT-Creation-Date: [^"\n]*', lambda match: 'POT-Creation-Date: ' + timestamp + r'\n', contents)
    path.write_text(contents)

CATALOG
sed -i "s/Project-Id-Version: PACKAGE VERSION/Project-Id-Version: Ticket Migration ${VERSION}/" locales/en_GB.po
msgattrib --clear-fuzzy --output-file=locales/en_GB.po locales/en_GB.po
msgmerge --no-fuzzy-matching locales/fr_FR.po locales/ticketmigration.pot -o locales/fr_FR.po.new
mv locales/fr_FR.po.new locales/fr_FR.po
msgfmt --check --check-format --statistics -o locales/en_GB.mo locales/en_GB.po
msgfmt --check --check-format --statistics -o locales/fr_FR.mo locales/fr_FR.po
for locale in en_GB fr_FR; do
  if msgattrib --untranslated "locales/${locale}.po" | grep -q '^msgid '; then
    echo "${locale} catalog contains untranslated messages" >&2
    exit 1
  fi
done

mkdir -p "${PACKAGE_DIR}"
rm -f "${ARCHIVE}"

# Explicit runtime allowlist keeps local attachments, credentials and tooling out.
for entry in setup.php hook.php composer.json composer.lock ticketmigration.xml \
  README.md CHANGELOG.md LICENSE SECURITY.md src front ajax templates public locales docs; do
  rsync -a "${entry}" "${PACKAGE_DIR}/"
done

(
  cd "${PACKAGE_DIR}"
  composer install --no-dev --no-interaction --prefer-dist \
    --optimize-autoloader --classmap-authoritative
)
rm -f "${PACKAGE_DIR}/composer.lock"

find "${PACKAGE_DIR}" -type f -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null

ARCHIVE="${ARCHIVE}" BUILD_DIR="${BUILD_DIR}" PLUGIN_KEY="${PLUGIN_KEY}" VERSION="${VERSION}" python3 - <<'PY'
import os
import pathlib
import zipfile

archive = pathlib.Path(os.environ['ARCHIVE'])
build_dir = pathlib.Path(os.environ['BUILD_DIR'])
plugin_dir = build_dir / os.environ['PLUGIN_KEY']
with zipfile.ZipFile(archive, 'w', zipfile.ZIP_DEFLATED) as output:
    for path in sorted(plugin_dir.rglob('*')):
        if path.is_file():
            info = zipfile.ZipInfo(str(path.relative_to(build_dir)), (1980, 1, 1, 0, 0, 0))
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = 0o100644 << 16
            output.writestr(info, path.read_bytes())
with zipfile.ZipFile(archive) as package:
    names = set(package.namelist())
    required = {'ticketmigration/setup.php', 'ticketmigration/hook.php',
                'ticketmigration/composer.json', 'ticketmigration/vendor/autoload.php'}
    missing = required - names
    if missing:
        raise SystemExit(f'Missing required entries: {sorted(missing)}')
    if any(not name.startswith('ticketmigration/') for name in names):
        raise SystemExit('Invalid archive root')
    forbidden = {'.local', '.agents', '.codex', '.aws', '.git', '.github',
                 '.idea', '.vscode', 'tests', 'scripts', 'dist', '.env', 'composer.lock'}
    if any(set(pathlib.PurePosixPath(name).parts) & forbidden
           or pathlib.PurePosixPath(name).name.startswith('.env.') for name in names):
        raise SystemExit('Development files found in archive')
    setup = package.read('ticketmigration/setup.php').decode()
    if f"define('PLUGIN_TICKETMIGRATION_VERSION', '{os.environ['VERSION']}');" not in setup:
        raise SystemExit('Archive version mismatch')
print(f'Verified {archive}: {len(names)} entries')
PY
