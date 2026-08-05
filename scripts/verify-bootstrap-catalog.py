#!/usr/bin/env python3
"""Validate and query the filesystem-backed FoxesCraft bootstrap catalog."""

from __future__ import annotations

import argparse
import hashlib
import json
import re
import stat
import sys
import tarfile
import zipfile
from dataclasses import dataclass
from pathlib import Path, PurePosixPath
from typing import Any, BinaryIO, Callable, Iterable

PLATFORM_RE = re.compile(
    r"^(?:(?:windows|linux)-(?:x86|x86_64|aarch64)|macos-(?:x86_64|aarch64))$"
)
VERSION_RE = re.compile(r"^(?:8u[0-9]+|[0-9]+(?:\.[0-9]+)+)$", re.IGNORECASE)
SAFE_NAME_RE = re.compile(r"^[A-Za-z0-9][A-Za-z0-9._+-]*$")
IGNORED_SUFFIXES = (".sha256", ".sig", ".part", ".tmp", ".bak", ".wrong")
MAX_ARCHIVE_ENTRIES = 200_000
MAX_EXPANDED_BYTES = 8 * 1024 * 1024 * 1024
MAX_RELEASE_BYTES = 1024 * 1024

PLATFORM_BRANCHES: dict[str, tuple[tuple[str, str], ...]] = {
    "windows-x86": (("win", "x32"), ("win", "x86"), ("windows", "x32"), ("windows", "x86")),
    "windows-x86_64": (
        ("win", "x64"), ("win", "amd64"), ("win", "x86_64"),
        ("windows", "x64"), ("windows", "amd64"), ("windows", "x86_64"),
    ),
    "windows-aarch64": (
        ("win", "arm64"), ("win", "aarch64"),
        ("windows", "arm64"), ("windows", "aarch64"),
    ),
    "linux-x86": (("linux", "x32"), ("linux", "x86"), ("unix", "x32"), ("unix", "x86")),
    "linux-x86_64": (
        ("linux", "x64"), ("linux", "amd64"), ("linux", "x86_64"),
        ("unix", "x64"), ("unix", "amd64"), ("unix", "x86_64"),
    ),
    "linux-aarch64": (
        ("linux", "arm64"), ("linux", "aarch64"),
        ("unix", "arm64"), ("unix", "aarch64"),
    ),
    "macos-x86_64": (
        ("mac", "x64"), ("macos", "x64"), ("osx", "x64"),
        ("mac", "x86_64"), ("macos", "x86_64"),
    ),
    "macos-aarch64": (
        ("mac", "arm64"), ("macos", "arm64"), ("osx", "arm64"),
        ("mac", "aarch64"), ("macos", "aarch64"),
    ),
}
BRANCH_PLATFORM = {
    tuple(part.lower() for part in branch): platform
    for platform, branches in PLATFORM_BRANCHES.items()
    for branch in branches
}


class CatalogError(RuntimeError):
    pass


@dataclass(frozen=True)
class ArchiveEntry:
    name: str
    size: int
    directory: bool
    link: bool
    locator: Any


def version_key(value: str) -> tuple[tuple[int, Any], ...]:
    return tuple(
        (1, int(part)) if part.isdigit() else (0, part.lower())
        for part in re.findall(r"\d+|[A-Za-z]+", value)
    )


def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def describe(storage: Path, path: Path) -> dict[str, Any]:
    resolved_storage = storage.resolve()
    resolved = path.resolve(strict=True)
    if path.is_symlink() or not resolved.is_file():
        raise CatalogError(f"artifact must be a regular non-symlink file: {path}")
    try:
        relative = resolved.relative_to(resolved_storage).as_posix()
    except ValueError as error:
        raise CatalogError(f"artifact escapes catalog storage: {path}") from error
    size = resolved.stat().st_size
    if size <= 0:
        raise CatalogError(f"artifact is empty: {relative}")
    return {
        "path": relative,
        "url": "/uploads/bootstrap/" + relative,
        "sha256": sha256_file(resolved),
        "size": size,
    }


def version_directories(root: Path) -> list[tuple[str, Path]]:
    if not root.is_dir():
        return []
    result = [
        (entry.name, entry)
        for entry in root.iterdir()
        if entry.is_dir() and not entry.is_symlink() and SAFE_NAME_RE.fullmatch(entry.name)
    ]
    return sorted(result, key=lambda item: version_key(item[0]), reverse=True)


def select_bootstrapper(storage: Path, platform: str) -> dict[str, Any]:
    preferred = "FoxesCraft.exe" if platform.startswith("windows-") else "FoxesCraft"
    for version, directory in version_directories(storage / "bootstrapper" / platform):
        files = [
            path
            for path in directory.iterdir()
            if path.is_file()
            and not path.is_symlink()
            and path.stat().st_size > 0
            and not path.name.lower().endswith(IGNORED_SUFFIXES)
            and (not platform.startswith("windows-") or path.suffix.lower() == ".exe")
        ]
        if not files:
            continue
        selected = next((path for path in files if path.name == preferred), None)
        selected = selected or sorted(files, key=lambda path: path.name.lower())[0]
        return {"version": version, "platform": platform, "artifact": describe(storage, selected)}
    raise CatalogError(f"no usable bootstrapper artifact for {platform}")


def scan_bootstrappers(storage: Path) -> dict[str, Any]:
    root = storage / "bootstrapper"
    if not root.is_dir():
        raise CatalogError(f"bootstrapper catalog does not exist: {root}")
    result: dict[str, Any] = {}
    for platform_dir in sorted(root.iterdir()):
        if platform_dir.is_dir() and PLATFORM_RE.fullmatch(platform_dir.name):
            result[platform_dir.name] = select_bootstrapper(storage, platform_dir.name)
    if not result:
        raise CatalogError("bootstrapper catalog has no supported platforms")
    return result


def select_launcher(storage: Path, file_name: str = "launcher.jar") -> dict[str, Any]:
    for version, directory in version_directories(storage / "launcher"):
        candidate = directory / file_name
        if candidate.is_file() and not candidate.is_symlink() and candidate.stat().st_size > 0:
            return {"version": version, "file_name": file_name, "artifact": describe(storage, candidate)}
    raise CatalogError("launcher catalog has no usable launcher.jar")


def archive_kind(file_name: str) -> str:
    lower = file_name.lower()
    if lower.endswith(".zip"):
        return "zip"
    if lower.endswith(".tar.gz") or lower.endswith(".tgz"):
        return "tar.gz"
    raise CatalogError(f"unsupported runtime archive extension: {file_name}")


def runtime_archive_name(file_name: str) -> str:
    lower = file_name.lower()
    name = file_name[:-7] if lower.endswith(".tar.gz") else file_name[:-4]
    if not name or not SAFE_NAME_RE.fullmatch(name):
        raise CatalogError(f"unsafe runtime archive name: {file_name}")
    return name


def archive_name_version(name: str) -> str:
    legacy = re.search(r"(?:jdk|jre|java)[-_]?(1\.8\.0[_-][0-9]+|8u[0-9]+)", name, re.IGNORECASE)
    if legacy:
        return version_core(legacy.group(1))
    match = re.search(r"(?:jdk|jre|java)[-_]?([0-9]+(?:\.[0-9]+)+)", name, re.IGNORECASE)
    if match:
        return version_core(match.group(1))
    legacy = re.match(r"^(1\.8\.0[_-][0-9]+|8u[0-9]+)(?:$|[-_+])", name, re.IGNORECASE)
    if legacy:
        return version_core(legacy.group(1))
    match = re.match(r"^([0-9]+(?:\.[0-9]+)+)(?:$|[-_+])", name)
    return version_core(match.group(1)) if match else ""


def normalize_entry_name(value: str) -> str:
    if "\x00" in value:
        raise CatalogError("archive entry contains a NUL byte")
    value = value.replace("\\", "/")
    while value.startswith("./"):
        value = value[2:]
    if not value or len(value) > 4096 or value.startswith("/") or re.match(r"^[A-Za-z]:/", value):
        raise CatalogError(f"archive entry is absolute, empty or too long: {value!r}")
    stripped = value.rstrip("/")
    parts = stripped.split("/")
    if any(part in {"", ".", ".."} for part in parts):
        raise CatalogError(f"archive entry contains an unsafe path component: {value}")
    return value


def parse_release(content: bytes) -> dict[str, str]:
    if len(content) > MAX_RELEASE_BYTES:
        raise CatalogError("runtime release metadata is unexpectedly large")
    result: dict[str, str] = {}
    for raw_line in content.decode("utf-8", "replace").splitlines():
        if "=" not in raw_line:
            continue
        key, value = raw_line.split("=", 1)
        value = value.strip()
        if len(value) >= 2 and value[0] == value[-1] == '"':
            value = bytes(value[1:-1], "utf-8").decode("unicode_escape")
        result[key.strip()] = value
    return result


def version_core(value: str) -> str:
    value = value.strip().strip('"')
    legacy = re.match(r"^1\.8\.0[_-]([0-9]+)", value, re.IGNORECASE)
    if legacy:
        return f"8u{int(legacy.group(1))}"
    legacy = re.match(r"^8u([0-9]+)", value, re.IGNORECASE)
    if legacy:
        return f"8u{int(legacy.group(1))}"
    match = re.match(r"^[0-9]+(?:\.[0-9]+)*", value)
    return match.group(0) if match else ""


def release_platform(release: dict[str, str]) -> str | None:
    os_name = release.get("OS_NAME", "").lower()
    arch = release.get("OS_ARCH", "").lower()
    if "windows" in os_name:
        os_key = "windows"
    elif "linux" in os_name:
        os_key = "linux"
    elif "mac" in os_name or "darwin" in os_name or "os x" in os_name:
        os_key = "macos"
    else:
        return None
    if arch in {"x86", "i386", "i486", "i586", "i686", "x86_32"}:
        arch_key = "x86"
    elif arch in {"x86_64", "amd64", "x64"}:
        arch_key = "x86_64"
    elif arch in {"aarch64", "arm64"}:
        arch_key = "aarch64"
    else:
        return None
    return f"{os_key}-{arch_key}"


def stable_version(value: str) -> bool:
    return re.search(r"(?:^|[-+._])(ea|alpha|beta|rc|snapshot)(?:$|[-+._0-9])", value, re.I) is None


def infer_vendor(path: str) -> str:
    lower = path.lower()
    vendors = {
        "temurin": "Eclipse Temurin",
        "liberica": "BellSoft Liberica",
        "bellsoft": "BellSoft Liberica",
        "corretto": "Amazon Corretto",
        "zulu": "Azul Zulu",
        "microsoft": "Microsoft OpenJDK",
        "graal": "GraalVM",
        "oracle": "Oracle",
    }
    return next((vendor for needle, vendor in vendors.items() if needle in lower), "OpenJDK-compatible")


def inspect_entries(
    entries: list[ArchiveEntry],
    read_entry: Callable[[Any], bytes],
    platform: str,
    inspection: str,
) -> dict[str, Any]:
    if len(entries) > MAX_ARCHIVE_ENTRIES:
        raise CatalogError("runtime archive contains too many entries")
    total_size = sum(entry.size for entry in entries)
    if total_size > MAX_EXPANDED_BYTES:
        raise CatalogError("runtime archive expands beyond the configured safety limit")
    if any(entry.link for entry in entries):
        linked = next(entry.name for entry in entries if entry.link)
        raise CatalogError(f"runtime archive contains a link: {linked}")

    expected_java = "java.exe" if platform.startswith("windows-") else "java"
    expected_javac = "javac.exe" if platform.startswith("windows-") else "javac"
    files: dict[str, ArchiveEntry] = {}
    for entry in entries:
        if entry.directory:
            continue
        lower = entry.name.lower()
        if lower in files:
            raise CatalogError(f"runtime archive contains a duplicate file path: {entry.name}")
        files[lower] = entry
    candidates: list[tuple[ArchiveEntry, tuple[str, ...]]] = []
    for entry in files.values():
        parts = tuple(PurePosixPath(entry.name).parts)
        if len(parts) >= 2 and parts[-2].lower() == "bin" and parts[-1].lower() == expected_java:
            candidates.append((entry, parts[:-2]))
    if not candidates:
        raise CatalogError(f"runtime archive has no bin/{expected_java}")

    roots = {tuple(part.lower() for part in root): (entry, root) for entry, root in candidates}
    selected: tuple[ArchiveEntry, tuple[str, ...]] | None = None
    if len(roots) == 1:
        selected = next(iter(roots.values()))
    else:
        # Java 8 full JDKs include a bundled <jdk>/jre/bin/java. The unique
        # candidate with bin/javac is the real JDK root, not a second runtime.
        jdk_roots = {
            key: candidate
            for key, candidate in roots.items()
            if (("/".join(key) + "/" if key else "") + f"bin/{expected_javac}") in files
        }
        if len(jdk_roots) == 1:
            selected = next(iter(jdk_roots.values()))
        else:
            release_roots = {
                key: candidate
                for key, candidate in roots.items()
                if (("/".join(key) + "/" if key else "") + "release") in files
            }
            if len(release_roots) == 1:
                selected = next(iter(release_roots.values()))
    if selected is None:
        raise CatalogError("runtime archive contains multiple ambiguous Java homes")
    _, root = selected
    prefix = "/".join(root)
    release_name = (prefix + "/release" if prefix else "release").lower()
    release_entry = files.get(release_name)
    if release_entry is None:
        raise CatalogError("runtime archive has no release metadata beside the selected Java home")
    release = parse_release(read_entry(release_entry.locator))
    version = release.get("JAVA_RUNTIME_VERSION") or release.get("JAVA_VERSION") or ""
    if not version:
        raise CatalogError("runtime release metadata has no Java version")
    javac_name = (prefix + f"/bin/{expected_javac}" if prefix else f"bin/{expected_javac}").lower()
    return {
        "vendor": release.get("IMPLEMENTOR") or release.get("JAVA_VENDOR") or "",
        "version": version.strip(),
        "platform": release_platform(release),
        "distribution": "jdk" if javac_name in files else "jre",
        "strip_components": len(root),
        "java_path": f"bin/{expected_java}",
        "inspection": inspection,
    }


def inspect_zip(path: Path, platform: str) -> dict[str, Any]:
    with zipfile.ZipFile(path) as archive:
        entries: list[ArchiveEntry] = []
        for index, info in enumerate(archive.infolist()):
            name = normalize_entry_name(info.filename)
            mode = (info.external_attr >> 16) & 0o170000
            entries.append(ArchiveEntry(
                name=name,
                size=max(0, info.file_size),
                directory=info.is_dir(),
                link=mode == stat.S_IFLNK,
                locator=index,
            ))
        return inspect_entries(entries, lambda index: archive.read(archive.infolist()[index]), platform, "zip-metadata")


def inspect_tar(path: Path, platform: str) -> dict[str, Any]:
    with tarfile.open(path, mode="r:*") as archive:
        members = archive.getmembers()
        entries = [ArchiveEntry(
            name=normalize_entry_name(member.name),
            size=max(0, member.size),
            directory=member.isdir(),
            link=member.issym() or member.islnk(),
            locator=member,
        ) for member in members]

        def read(member: tarfile.TarInfo) -> bytes:
            stream: BinaryIO | None = archive.extractfile(member)
            if stream is None:
                raise CatalogError(f"cannot read TAR entry: {member.name}")
            return stream.read(MAX_RELEASE_BYTES + 1)

        return inspect_entries(entries, read, platform, "tar-metadata")


def inspect_runtime(storage: Path, path: Path, platform: str, branch: tuple[str, str]) -> dict[str, Any]:
    relative = path.resolve().relative_to(storage.resolve()).as_posix()
    name = runtime_archive_name(path.name)
    kind = archive_kind(path.name)
    inspected = inspect_zip(path, platform) if kind == "zip" else inspect_tar(path, platform)
    core = version_core(inspected["version"])
    if not core:
        raise CatalogError(f"cannot read contained Java version: {relative}")
    file_version = archive_name_version(name)
    if file_version and file_version != core:
        raise CatalogError(
            f"archive filename version {file_version} disagrees with contained Java version {core}: {relative}"
        )
    detected_platform = inspected["platform"] or platform
    if detected_platform != platform:
        raise CatalogError(
            f"runtime branch {platform} disagrees with archive metadata {detected_platform}: {relative}"
        )
    vendor = inspected["vendor"] or infer_vendor(relative)
    artifact = describe(storage, path)
    return {
        **artifact,
        "catalog_branch": "/".join(branch),
        "platform": platform,
        "name": name,
        "install_path": f"runtime/{name}",
        "file_name": path.name,
        "archive": kind,
        "inspection": inspected["inspection"],
        "java_path": inspected["java_path"],
        "strip_components": inspected["strip_components"],
        "version": inspected["version"],
        "version_core": core,
        "java_major": 8 if core.lower().startswith("8u") else int(core.split(".", 1)[0]),
        "vendor": vendor,
        "distribution": inspected["distribution"],
        "stable": stable_version(inspected["version"]),
    }


def runtime_archives(root: Path) -> Iterable[tuple[Path, str, tuple[str, str]]]:
    for path in sorted(root.rglob("*")):
        if not path.is_file() or path.is_symlink() or path.name.lower().endswith(IGNORED_SUFFIXES):
            continue
        try:
            archive_kind(path.name)
        except CatalogError:
            continue
        relative = path.relative_to(root)
        if len(relative.parts) < 3:
            yield path, "", ("", "")
            continue
        branch = (relative.parts[0].lower(), relative.parts[1].lower())
        yield path, BRANCH_PLATFORM.get(branch, ""), branch


def score(candidate: dict[str, Any], distribution: str, vendor: str) -> int:
    value = 1000 + (200 if candidate["inspection"] in {"zip-metadata", "tar-metadata"} else 0)
    value += 100 if candidate["stable"] else 0
    if distribution == "any":
        value += 40 if candidate["distribution"] == "jdk" else 30
    elif candidate["distribution"] == distribution:
        value += 80
    if vendor and vendor.lower() in candidate["vendor"].lower():
        value += 120
    value += max(0, 20 - candidate["path"].count("/"))
    return value


def scan_runtime_catalog(storage: Path) -> dict[str, Any]:
    root = storage / "runtime"
    if not root.is_dir():
        raise CatalogError(f"runtime catalog does not exist: {root}")
    candidates: list[dict[str, Any]] = []
    rejected: list[dict[str, str]] = []
    for path, platform, branch in runtime_archives(root):
        relative = path.relative_to(storage).as_posix()
        if not platform:
            rejected.append({"path": relative, "reason": "archive is outside a supported platform branch"})
            continue
        try:
            candidates.append(inspect_runtime(storage, path, platform, branch))
        except (OSError, tarfile.TarError, zipfile.BadZipFile, CatalogError) as error:
            rejected.append({"path": relative, "reason": str(error)})
    if not candidates:
        raise CatalogError("runtime catalog has no usable archives")
    return {
        "mode": "exact-version, platform-branch, archive-metadata",
        "layout": "runtime/<os-alias>/<arch-alias>/<archive>",
        "candidates": candidates,
        "rejected": rejected,
    }


def select_runtime(
    catalog: dict[str, Any],
    platform: str,
    version: str,
    distribution: str,
    vendor: str,
    allow_prerelease: bool,
) -> dict[str, Any]:
    compatible = [
        candidate
        for candidate in catalog["candidates"]
        if candidate["platform"] == platform
        and candidate["version_core"] == version
        and (distribution == "any" or candidate["distribution"] == distribution)
        and (allow_prerelease or candidate["stable"])
    ]
    if not compatible:
        raise CatalogError(f"no runtime matches platform={platform}, exact version={version}, distribution={distribution}")
    for candidate in compatible:
        candidate["score"] = score(candidate, distribution, vendor)
    compatible.sort(key=lambda candidate: (-candidate["score"], candidate["path"].lower()))
    selected = compatible[0]
    return {
        "request": {
            "platform": platform,
            "version": version,
            "distribution": distribution,
            "vendor": vendor,
            "allow_prerelease": allow_prerelease,
        },
        "compatible_archives": len(compatible),
        "selected": selected,
    }


def validate_catalog(storage: Path, args: argparse.Namespace) -> dict[str, Any]:
    storage = storage.resolve()
    if not storage.is_dir():
        raise CatalogError(f"storage directory does not exist: {storage}")
    runtime_catalog = scan_runtime_catalog(storage)
    result: dict[str, Any] = {
        "ok": True,
        "storage": str(storage),
        "source_of_truth": "filesystem-and-archive-metadata",
        "bootstrapper": scan_bootstrappers(storage),
        "launcher": select_launcher(storage),
        "runtime_catalog": runtime_catalog,
    }
    if args.platform or args.version:
        if not args.platform or not args.version:
            raise CatalogError("--platform and --version must be used together")
        result["runtime_selection"] = select_runtime(
            runtime_catalog,
            args.platform,
            args.version,
            args.distribution,
            args.vendor,
            args.allow_prerelease,
        )
    return result


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("storage", type=Path, help="uploads/bootstrap directory to verify")
    parser.add_argument("--platform", choices=sorted(PLATFORM_BRANCHES))
    parser.add_argument("--version", help="exact dotted Java version, for example 17.0.16")
    parser.add_argument("--distribution", choices=("any", "jdk", "jre"), default="any")
    parser.add_argument("--vendor", default="")
    parser.add_argument("--allow-prerelease", action="store_true")
    args = parser.parse_args()
    if args.version and not VERSION_RE.fullmatch(args.version):
        parser.error("--version must be an exact dotted Java version")
    try:
        result = validate_catalog(args.storage, args)
    except CatalogError as error:
        print(json.dumps({"ok": False, "error": str(error)}, ensure_ascii=False), file=sys.stderr)
        return 1
    print(json.dumps(result, ensure_ascii=False, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
