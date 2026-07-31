#!/usr/bin/env bash
set -euo pipefail

SOURCE_ROOT="${1:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
TARGET_ROOT="${2:-/var/www/FoxCMS}"
WEB_GROUP="${FOXESCRAFT_WEB_GROUP:-}"
SYNC_BOOTSTRAP_CATALOG="${FOXESCRAFT_SYNC_BOOTSTRAP_CATALOG:-true}"

if [[ ! -f "${SOURCE_ROOT}/engine/bootstrap.php" || ! -f "${SOURCE_ROOT}/index.php" ]]; then
  echo "Invalid FoxCMS source root: ${SOURCE_ROOT}" >&2
  exit 2
fi

if [[ ! -d "${TARGET_ROOT}" ]]; then
  echo "FoxCMS target does not exist: ${TARGET_ROOT}" >&2
  exit 2
fi

command -v rsync >/dev/null 2>&1 || {
  echo "rsync is required" >&2
  exit 2
}
command -v python3 >/dev/null 2>&1 || {
  echo "python3 is required" >&2
  exit 2
}

if command -v php >/dev/null 2>&1 && [[ -f "${TARGET_ROOT}/.env" ]]; then
  set +e
  FOXESCRAFT_ENV_FILE="${TARGET_ROOT}/.env" php "${SOURCE_ROOT}/scripts/migrate.php" --status >/tmp/foxescraft-migration-status.json
  migration_status=$?
  set -e
  if [[ ${migration_status} -eq 2 ]]; then
    if [[ "${FOXESCRAFT_RUN_MIGRATIONS:-false}" != "true" ]]; then
      cat /tmp/foxescraft-migration-status.json >&2
      echo "Pending database migrations. Re-run with FOXESCRAFT_RUN_MIGRATIONS=true and a schema-owner FOXESCRAFT_MIGRATION_DB_* environment." >&2
      exit 3
    fi
    FOXESCRAFT_ENV_FILE="${TARGET_ROOT}/.env" php "${SOURCE_ROOT}/scripts/migrate.php"
  elif [[ ${migration_status} -ne 0 ]]; then
    cat /tmp/foxescraft-migration-status.json >&2 || true
    echo "Unable to verify database migration state; deployment aborted before synchronization." >&2
    exit 3
  fi
  rm -f /tmp/foxescraft-migration-status.json
elif [[ "${FOXESCRAFT_ALLOW_UNVERIFIED_SCHEMA:-false}" != "true" ]]; then
  echo "PHP CLI and ${TARGET_ROOT}/.env are required to verify migrations. Set FOXESCRAFT_ALLOW_UNVERIFIED_SCHEMA=true only for a deliberate offline deployment." >&2
  exit 3
fi

rsync -a --delete-delay \
  --exclude='.git/' \
  --exclude='.idea/' \
  --exclude='.springsuite/' \
  --exclude='.springsuite-repository.json' \
  --exclude='.env' \
  --exclude='uploads/' \
  --exclude='engine/cache/logs/' \
  --exclude='templates/*/node_modules/' \
  --exclude='templates/*/.vite/' \
  --exclude='templates/*/npm-debug.log*' \
  "${SOURCE_ROOT}/" "${TARGET_ROOT}/"

if [[ "${SYNC_BOOTSTRAP_CATALOG}" == "true" ]]; then
  FOXESCRAFT_WEB_GROUP="${WEB_GROUP}" \
    bash "${SOURCE_ROOT}/scripts/publish-bootstrap-production.sh" \
    "${SOURCE_ROOT}" "${TARGET_ROOT}"
fi

if [[ -z "${WEB_GROUP}" ]]; then
  for candidate in www-data nginx apache; do
    if getent group "${candidate}" >/dev/null 2>&1; then
      WEB_GROUP="${candidate}"
      break
    fi
  done
fi

mkdir -p \
  "${TARGET_ROOT}/engine/cache/logs" \
  "${TARGET_ROOT}/engine/cache/tmp" \
  "${TARGET_ROOT}/uploads"

if [[ -n "${WEB_GROUP}" ]]; then
  chgrp -R "${WEB_GROUP}" \
    "${TARGET_ROOT}/engine/cache/logs" \
    "${TARGET_ROOT}/engine/cache/tmp" \
    "${TARGET_ROOT}/uploads"
  chmod 2775 \
    "${TARGET_ROOT}/engine/cache/logs" \
    "${TARGET_ROOT}/engine/cache/tmp" \
    "${TARGET_ROOT}/uploads"
  chmod -R g+rwX \
    "${TARGET_ROOT}/engine/cache/logs" \
    "${TARGET_ROOT}/engine/cache/tmp" \
    "${TARGET_ROOT}/uploads"
  echo "Runtime writable directories assigned to group: ${WEB_GROUP}"
else
  chmod 0775 \
    "${TARGET_ROOT}/engine/cache/logs" \
    "${TARGET_ROOT}/engine/cache/tmp" \
    "${TARGET_ROOT}/uploads"
  echo "Warning: no web-server group detected. Set FOXESCRAFT_WEB_GROUP explicitly if PHP-FPM cannot write runtime files." >&2
fi

python3 "${TARGET_ROOT}/scripts/verify-deployment.py" "${TARGET_ROOT}"

if command -v php >/dev/null 2>&1; then
  while IFS= read -r -d '' php_file; do
    php -l "${php_file}" >/dev/null
  done < <(find "${TARGET_ROOT}" -type f -name '*.php' -print0)
  echo "PHP lint passed for deployed sources."

  php "${TARGET_ROOT}/scripts/diagnose-runtime.php" --no-db
  if [[ "${FOXESCRAFT_MIGRATE_USER_STORAGE:-false}" == "true" || "${FOXESCRAFT_RUN_MIGRATIONS:-false}" == "true" ]]; then
    FOXESCRAFT_ENV_FILE="${TARGET_ROOT}/.env" php "${TARGET_ROOT}/scripts/migrate-user-storage.php"
  fi
else
  echo "PHP CLI was not found; deployment structure was verified without php -l or runtime diagnostics."
fi

echo "FoxCMS synchronized to ${TARGET_ROOT}. Restart PHP-FPM or Apache to clear OPcache."
echo "For a database test run: php ${TARGET_ROOT}/scripts/diagnose-runtime.php"
echo "Runtime errors: ${TARGET_ROOT}/engine/cache/logs/runtime.log"
if [[ "${SYNC_BOOTSTRAP_CATALOG}" != "true" ]]; then
  echo "Bootstrap catalog was deliberately preserved because FOXESCRAFT_SYNC_BOOTSTRAP_CATALOG=${SYNC_BOOTSTRAP_CATALOG}. Publish them explicitly with scripts/publish-bootstrap-production.sh."
fi
