# Admin server Java runtime selection

The Java runtime field is populated from archive file names below:

```text
FOXESCRAFT_BOOTSTRAP_STORAGE_DIRECTORY/runtime
```

Production default:

```text
/var/www/FoxCMS/uploads/bootstrap/runtime
```

## Administrative catalog

The administrative scanner recursively lists `.zip`, `.tar.gz` and `.tgz` files. It does not open or unpack them.

It derives:

- the complete Java version from the archive filename;
- the Java major family from that version;
- the operating-system family from the relative path or filename.

System aliases:

- Windows: `win`, `windows`, `win32`, `win64`;
- Linux: `linux`, `unix`;
- macOS: `mac`, `macos`, `osx`, `darwin`.

Architecture names are ignored.

The select is grouped by Java major, not exact patch version. A family appears when at least one archive from that major exists for Windows, Linux and macOS. Patch versions may differ between systems.

With the current repository catalog the options are:

```text
JDK 25 — Windows 25.0.2 / Linux 25.0.2 / macOS 25.0.1
JDK 17 — Windows 17.0.20 / Linux 17.0.13 / macOS 17.0.13
JDK 11 — Windows 11.0.29 / Linux 11.0.29 / macOS 11.0.29
```

The database stores the major family (`25`, `17`, or `11`). Existing exact values such as `17.0.16` are normalized to `17` when edited and saved.

## Bootstrap selection

The bootstrap runtime API accepts either:

```text
version=17          # major mode
version=17.0.16     # exact mode
```

Major mode selects the newest available patch from the requested major for the current platform. Exact mode preserves exact-version matching.

The public archive scanner also supports archives without a `release` file: version falls back to the archive filename and platform falls back to the selected catalog branch.


## Persistence and readiness

The runtime catalog is a readiness diagnostic and a source of selectable JDK families. It is not a database-write dependency.

- A disabled server may be saved without `jreVersion` as a draft.
- An enabled server must have a numeric Java major version.
- Existing numeric legacy values remain saveable even when the administrative catalog cannot confirm them.
- Failure to read the runtime directory or absence of a cross-platform archive family does not reject `saveServer`.
- The server configuration is saved and the API returns a `warning` response explaining that startup may remain unavailable until the required archives are installed.

This separation allows administrators to edit names, ports, groups, descriptions, images and other server settings while runtime storage is temporarily unavailable.
