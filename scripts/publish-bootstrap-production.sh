#!/usr/bin/env bash
set -euo pipefail

SOURCE_ROOT="${1:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
TARGET_ROOT="${2:-/var/www/FoxCMS}"
SOURCE_STORAGE="${SOURCE_ROOT}/uploads/bootstrap"
TARGET_STORAGE="${TARGET_ROOT}/uploads/bootstrap"
VERIFIER="${SOURCE_ROOT}/scripts/verify-bootstrap-catalog.py"
WEB_GROUP="${FOXESCRAFT_WEB_GROUP:-}"

fail() {
  echo "Bootstrap publication failed: $*" >&2
  exit 2
}

[[ -d "${SOURCE_STORAGE}" ]] || fail "source storage does not exist: ${SOURCE_STORAGE}"
[[ -f "${VERIFIER}" ]] || fail "catalog verifier is missing: ${VERIFIER}"
[[ -d "${TARGET_ROOT}" ]] || fail "target FoxCMS root does not exist: ${TARGET_ROOT}"
command -v python3 >/dev/null 2>&1 || fail "python3 is required"
command -v rsync >/dev/null 2>&1 || fail "rsync is required"

source_inventory="$(mktemp)"
cleanup() {
  rm -f "${source_inventory}" "${response_file:-}"
}
trap cleanup EXIT

python3 "${VERIFIER}" "${SOURCE_STORAGE}" >"${source_inventory}"
mkdir -p "${TARGET_STORAGE}"

# Version directories and files are the release catalog. rsync writes through
# temporary files, so the manifest cannot hash a partially copied artifact.
rsync -a --checksum --delay-updates \
  --exclude='release.json' \
  --exclude='.release.json.*' \
  "${SOURCE_STORAGE}/" "${TARGET_STORAGE}/"

# The descriptor is obsolete; the server derives everything from the files.
rm -f "${TARGET_STORAGE}/release.json" "${TARGET_STORAGE}"/.release.json.*
python3 "${VERIFIER}" "${TARGET_STORAGE}" >/dev/null

if [[ -z "${WEB_GROUP}" ]]; then
  for candidate in www-data nginx apache; do
    if getent group "${candidate}" >/dev/null 2>&1; then
      WEB_GROUP="${candidate}"
      break
    fi
  done
fi

if [[ -n "${WEB_GROUP}" ]]; then
  chgrp -R "${WEB_GROUP}" "${TARGET_STORAGE}"
  find "${TARGET_STORAGE}" -type d -exec chmod 2755 {} +
  find "${TARGET_STORAGE}" -type f -exec chmod 0644 {} +
fi

manifest_url="${FOXESCRAFT_BOOTSTRAP_MANIFEST_URL:-}"
if [[ -n "${manifest_url}" ]] && command -v curl >/dev/null 2>&1; then
  response_file="$(mktemp)"
  cache_buster="publish_$(date +%s)_$$"
  if [[ "${manifest_url}" == *\?* ]]; then
    smoke_url="${manifest_url}&publication=${cache_buster}"
  else
    smoke_url="${manifest_url}?publication=${cache_buster}"
  fi

  status="$(curl --silent --show-error --location \
    --connect-timeout 10 --max-time 60 \
    --output "${response_file}" --write-out '%{http_code}' \
    -H 'Accept: application/json' \
    -H 'Cache-Control: no-cache' \
    -H 'Pragma: no-cache' \
    "${smoke_url}")"
  if [[ "${status}" != "200" ]]; then
    cat "${response_file}" >&2 || true
    fail "manifest smoke test returned HTTP ${status}: ${smoke_url}"
  fi

  python3 - "${source_inventory}" "${response_file}" <<'PY'
import json
import sys

inventory_path, manifest_path = sys.argv[1:3]
with open(inventory_path, "r", encoding="utf-8") as handle:
    inventory = json.load(handle)
with open(manifest_path, "r", encoding="utf-8") as handle:
    manifest = json.load(handle)

errors = []
actual_bootstrapper = manifest.get("bootstrapper", {})
actual_platforms = actual_bootstrapper.get("artifacts", {})
expected_platforms = inventory.get("bootstrapper", {})

if not isinstance(actual_platforms, dict) or not actual_platforms:
    errors.append("manifest bootstrapper.artifacts is empty")
else:
    for platform, actual_artifact in actual_platforms.items():
        expected = expected_platforms.get(platform)
        if not isinstance(expected, dict):
            errors.append(f"unexpected bootstrapper platform: {platform}")
            continue
        expected_artifact = expected.get("artifact", {})
        if actual_bootstrapper.get("version") != expected.get("version"):
            errors.append(
                f"bootstrapper.version mismatch for {platform}: "
                f"expected={expected.get('version')!r} actual={actual_bootstrapper.get('version')!r}"
            )
        for field in ("sha256", "size", "url"):
            if actual_artifact.get(field) != expected_artifact.get(field):
                errors.append(
                    f"bootstrapper.{platform}.{field} mismatch: "
                    f"expected={expected_artifact.get(field)!r} actual={actual_artifact.get(field)!r}"
                )

expected_launcher = inventory.get("launcher", {})
actual_launcher = manifest.get("launcher", {})
if actual_launcher.get("version") != expected_launcher.get("version"):
    errors.append(
        "launcher.version mismatch: "
        f"expected={expected_launcher.get('version')!r} actual={actual_launcher.get('version')!r}"
    )
expected_artifact = expected_launcher.get("artifact", {})
actual_artifact = actual_launcher.get("artifact", {})
for field in ("sha256", "size", "url"):
    if actual_artifact.get(field) != expected_artifact.get(field):
        errors.append(
            f"launcher.artifact.{field} mismatch: "
            f"expected={expected_artifact.get(field)!r} actual={actual_artifact.get(field)!r}"
        )

if errors:
    print("Published dynamic manifest does not match filesystem catalog:", file=sys.stderr)
    for error in errors:
        print(f"- {error}", file=sys.stderr)
    raise SystemExit(2)
PY

  echo "Dynamic manifest smoke test passed: ${smoke_url}"
fi

echo "Bootstrap filesystem catalog published to ${TARGET_STORAGE}"
