# FoxesCraft Bootstrap API

Public endpoints:

```text
/api/bootstrap/manifest.php
/api/bootstrap/download.php?platform=windows-x86_64
```

## Runtime request contract

The bootstrapper detects its current platform and requests one exact Java version:

```http
POST /api/bootstrap/manifest.php
  ?platform=windows-x86_64
  &os=windows
  &arch=x86_64
  &version=17
  &client_version=0.4.3
Content-Type: application/json

{
  "schemaVersion": 1,
  "systemHWID": "<domain-separated SHA-256>",
  "platform": "windows-x86_64",
  "updaterVersion": "0.4.3",
  "systemInformation": {
    "os": {
      "name": "windows",
      "version": "Windows 11",
      "kernel": "10.0.26100",
      "architecture": "x86_64"
    },
    "cpu": {
      "brand": "CPU model",
      "logicalCores": 16
    },
    "memory": {
      "totalBytes": 34359738368
    },
    "gpu": {
      "adapters": ["GPU model"]
    }
  }
}
```

The client does not provide a JDK path, archive name, bitness directory, hash,
size, Java executable path or extraction layout. Those values are discovered by
the API from the published filesystem and archive metadata.

## Hardware inventory

UpdaterNorth sends the hardware capability report in the manifest POST request.
It never sends the raw Windows MachineGuid, Linux machine-id, macOS platform UUID,
serial numbers, MAC addresses, user name or host name. `systemHWID` is a
domain-separated SHA-256 value generated locally.

`system_hardware_inventory.systemHWID` is the primary key. The API uses
`INSERT IGNORE`, so the first report for a system is stored and later requests
for the same `systemHWID` do not overwrite it. The response header describes
the result:

```text
X-FoxesCraft-Hardware-Inventory: inserted|existing|unavailable|disabled|not-provided
```

Hardware persistence is fail-open: a temporary database problem is logged and
reported as `unavailable`, while the bootstrap manifest is still generated.
Malformed or privacy-unsafe reports are rejected with a 4xx response.


Optional runtime filters supported by the resolver:

```text
&distribution=any|jdk|jre
&vendor=BellSoft
&allow_prerelease=false
```

The default policy is `distribution=any` and prerelease runtimes disabled.

Version selection has two modes:

- `version=17` selects the newest available stable `17.x` archive for the requested platform;
- `version=17.0.16` keeps exact-version behavior and cannot select `17.0.20`.

## Storage layout

```text
uploads/bootstrap/
├── bootstrapper/
│   └── windows-x86_64/
│       └── 0.4.1/FoxesCraft.exe
├── launcher/
│   └── 2.12.0-Emberstone/launcher.jar
└── runtime/
    ├── win/
    │   ├── x32/
    │   ├── x64/
    │   │   ├── jdk-17.0.16.zip
    │   │   └── jdk-17.0.20.zip
    │   └── arm64/
    ├── linux/
    │   ├── x32/
    │   ├── x64/
    │   └── arm64/
    └── mac/
        ├── x64/
        └── arm64/
```

The resolver also recognizes common aliases such as `windows/x64`,
`win/amd64`, `linux/x86_64`, `unix/x64`, `macos/arm64` and `osx/x64`. Only
branches that map to the requested platform are scanned.

## Runtime scanner

`runtime-catalog.php` is now a stable facade. The implementation lives in:

```text
api/bootstrap/runtime-catalog/
├── request.php      request parsing and validation
├── platform.php     platform aliases and branch mapping
├── filesystem.php   safe catalog traversal
├── metadata.php     version, filename and vendor helpers
├── archive.php      archive-independent Java-home analysis
├── zip.php          ZIP inspection
├── tar.php          TAR.GZ/TGZ inspection
├── selection.php    compatibility, ranking and diagnostics
└── resolver.php     orchestration and response assembly
```

The facade remains the only include used by `manifest.php`, so the public entry
point is unchanged.

The runtime catalog supports:

- `.zip`, `.tar.gz` and `.tgz` archives;
- Windows x86, x86-64 and ARM64;
- Linux x86, x86-64 and ARM64;
- macOS x86-64 and ARM64;
- JDK and JRE layouts with or without one outer archive directory;
- archives with or without a Java `release` file;
- vendor detection from `release` metadata with path fallback;
- version detection from `release` metadata with archive-filename fallback;
- deterministic selection when several exact-version candidates exist.

For each candidate the scanner:

1. validates that the file is regular, readable, non-empty and not a symlink;
2. validates every archive entry against absolute paths, `..`, NUL bytes and
   excessive path length;
3. rejects archive symlinks and ambiguous archives containing several Java
   homes;
4. locates `bin/java` or `bin/java.exe`;
5. reads the matching `release` file when it exists;
6. falls back to the archive filename and catalog branch when `release` is absent;
7. detects `strip_components` and the relative Java executable path;
8. verifies that available filename metadata, archive metadata and catalog branch
   do not contradict one another;
9. compares either the exact version or Java major, depending on the request mode;
10. calculates SHA-256 and size for the selected artifact.

The local installation directory name is extracted only from the archive
filename:

```text
runtime/win/x64/jdk-17.0.16.zip
→ name: jdk-17.0.16
→ install_path: runtime/jdk-17.0.16
```

The archive's internal root may have another name, for example
`jdk-17.0.16-win64/`; `strip_components` removes it during extraction.

## Runtime response

Example descriptor returned by the API:

```json
{
  "runtime_id": "bellsoft-jdk-17.0.16-12-lts-windows-x86_64-jdk-17.0.16",
  "url": "/uploads/bootstrap/runtime/win/x64/jdk-17.0.16.zip",
  "sha256": "calculated-from-the-selected-archive",
  "size": 197876625,
  "name": "jdk-17.0.16",
  "install_path": "runtime/jdk-17.0.16",
  "java_path": "bin/java.exe",
  "file_name": "jdk-17.0.16.zip",
  "archive": "zip",
  "strip_components": 1,
  "vendor": "BellSoft",
  "distribution": "jdk",
  "version": "17.0.16+12-LTS",
  "java_major": 17,
  "platform": "windows-x86_64",
  "inspection": "zip-metadata"
}
```

The bootstrapper verifies the returned SHA-256 and size while downloading. It
then executes the extracted Java and independently checks the exact version and
reported platform before activation.

## Other artifacts

Bootstrapper and launcher versions are discovered from their version
directories. SHA-256 and size are calculated from the actual selected files, so
stored metadata cannot drift away from published bytes.

## Configuration

```text
FOXESCRAFT_BOOTSTRAP_STORAGE_DIRECTORY=/var/www/FoxCMS/uploads/bootstrap
FOXESCRAFT_BOOTSTRAP_CACHE_MAX_AGE=60
```

Launcher JVM arguments remain server policy in `api/bootstrap/config.php`.

## Verification

Validate the complete catalog:

```bash
python3 scripts/verify-bootstrap-catalog.py uploads/bootstrap
```

Validate a runtime selection:

```bash
# Major mode: newest available 17.x for the platform
python3 scripts/verify-bootstrap-catalog.py uploads/bootstrap \
  --platform windows-x86_64 \
  --version 17

# Exact mode
python3 scripts/verify-bootstrap-catalog.py uploads/bootstrap \
  --platform windows-x86_64 \
  --version 17.0.16
```

Optional verifier filters mirror the API:

```text
--distribution any|jdk|jre
--vendor BellSoft
--allow-prerelease
```

## Publishing

```bash
sudo FOXESCRAFT_WEB_GROUP=www-data \
  FOXESCRAFT_BOOTSTRAP_MANIFEST_URL='https://foxescraft.ru/api/bootstrap/manifest.php?platform=windows-x86_64&version=17.0.16&client_version=deploy-smoke' \
  bash scripts/publish-bootstrap-production.sh \
  /path/to/FoxCMS \
  /var/www/FoxCMS
```
