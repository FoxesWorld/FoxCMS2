# FoxesCraft Bootstrap API

Public endpoints:

```text
/api/bootstrap/manifest.php
/api/bootstrap/download.php?platform=windows-x86_64
```

## Runtime request contract

The bootstrapper detects its current platform and requests one exact Java version:

```text
GET /api/bootstrap/manifest.php
  ?platform=windows-x86_64
  &os=windows
  &arch=x86_64
  &version=17.0.16
  &client_version=0.4.1
```

The client does not provide a JDK path, archive name, bitness directory, hash,
size, Java executable path or extraction layout. Those values are discovered by
the API from the published filesystem and archive metadata.

Optional runtime filters supported by the resolver:

```text
&distribution=any|jdk|jre
&vendor=BellSoft
&allow_prerelease=false
```

The default policy is `distribution=any` and prerelease runtimes disabled.
Version selection is always exact: requesting `17.0.16` cannot select
`17.0.20`.

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
`win/amd64`, `linux/x86_64`, `macos/arm64` and `osx/x64`. Only branches that
map to the requested platform are scanned.

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
- vendor detection from the Java `release` file with filename fallback;
- deterministic selection when several exact-version candidates exist.

For each candidate the scanner:

1. validates that the file is regular, readable, non-empty and not a symlink;
2. validates every archive entry against absolute paths, `..`, NUL bytes and
   excessive path length;
3. rejects archive symlinks and ambiguous archives containing several Java
   homes;
4. locates `bin/java` or `bin/java.exe`;
5. locates the matching `release` file beside that Java home;
6. detects `strip_components` and the relative Java executable path;
7. reads the contained Java version, vendor, OS and architecture;
8. verifies that the filename version, archive metadata and catalog branch do
   not contradict one another;
9. compares the contained version with the exact requested version;
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

Validate an exact runtime selection:

```bash
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
