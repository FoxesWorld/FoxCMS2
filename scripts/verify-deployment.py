#!/usr/bin/env python3
"""Verify a deployed FoxCMS runtime, UUID identity contract and selected theme."""

from __future__ import annotations

import argparse
import hashlib
import json
import os
import re
import sys
from pathlib import Path

STATIC_REQUIRED_FILES = {
    "index.php": "engine/bootstrap.php",
    "engine/bootstrap.php": "RuntimeErrorHandler::register",
    "engine/Application.class.php": "ApplicationKernel",
    "engine/SystemRequests.class.php": "final class SystemRequests",
    "engine/classes/config/AppConfigFactory.class.php": "final class AppConfigFactory",
    "engine/classes/database/MigrationRunner.class.php": "final class MigrationRunner",
    "engine/classes/frontend/FrontendRegistry.class.php": "final class FrontendRegistry",
    "engine/classes/http/NetworkContext.class.php": "final class NetworkContext",
    "engine/classes/http/SecurityHeaders.class.php": "final class SecurityHeaders",
    "engine/classes/identity/UserIdentityException.class.php": "final class UserIdentityException",
    "engine/classes/identity/Uuid.class.php": "final class Uuid",
    "engine/classes/security/RememberToken.class.php": "final class RememberToken",
    "engine/classes/modules/AdminPanel/AdminAchievementController.class.php": "final class AdminAchievementController",
    "engine/classes/services/HealthCheckService.class.php": "identityCheck",
    "engine/classes/services/LauncherSessionService.class.php": "userUuid",
    "engine/classes/services/RuntimeJdkCatalog.class.php": "RuntimeMetadata::runtimeNormalizeVersion",
    "engine/classes/session/UserSession.class.php": "public function uuid()",
    "engine/classes/support/RuntimeErrorHandler.class.php": "final class RuntimeErrorHandler",
    "engine/classes/themes/ThemeResolver.class.php": "final class ThemeResolver",
    "engine/classes/themes/ThemePageStorage.class.php": "final class ThemePageStorage",
    "engine/classes/themes/ThemeContentRepository.class.php": "final class ThemeContentRepository",
    "engine/classes/themes/ThemeRuntimeTplDocument.class.php": "final class ThemeRuntimeTplDocument",
    "engine/classes/themes/ThemeRuntimeTplCompiler.class.php": "final class ThemeRuntimeTplCompiler",
    "engine/classes/themes/ThemeUserOptionsRepository.class.php": "final class ThemeUserOptionsRepository",
    "engine/classes/themes/ThemePageTemplateRepository.class.php": "final class ThemePageTemplateRepository",
    "engine/classes/themes/ThemeBadgePageRepository.class.php": "final class ThemeBadgePageRepository",
    "engine/classes/themes/ThemeRenderer.class.php": "'uuid'",
    "engine/classes/syslib/database.php": "final class db",
    "engine/classes/syslib/functions": "final class functions",
    "engine/data/const.php": "locale.ru.php",
    "engine/data/environment.php": "function foxLoadEnv",
    "engine/data/locale.ru.php": "authWrong",
    "engine/data/modules.json": '"AuthManager"',
    "templates/foxengine2/data/badges/earlyuser.html": "data-badge-history",
    "authlib/AuthlibSessionRepository.class.php": "userUuid",
    "api/autoload.php": "dirname(__DIR__) . '/autoload.php'",
    "api/content.php": "ContentApiApplication",
    "api/src/FoxCMS/Api/Content/ContentApiApplication.php": "requireRegistryDependencies",
    "api/health.php": "HealthApiApplication",
    "api/src/FoxCMS/Api/Content/BadgeCatalogService.php": "final class BadgeCatalogService",
    "api/src/FoxCMS/Api/Bootstrap/BootstrapConfig.php": "FOXESCRAFT_BOOTSTRAP_STORAGE_DIRECTORY",
    "api/src/FoxCMS/Api/Bootstrap/ManifestController.php": "final class ManifestController",
    "api/src/FoxCMS/Api/Bootstrap/RuntimeCatalog.php": "final class RuntimeCatalog",
    "api/src/FoxCMS/Api/Bootstrap/Runtime/RuntimeRequest.php": "final class RuntimeRequest",
    "api/src/FoxCMS/Api/Bootstrap/Runtime/RuntimePlatform.php": "final class RuntimePlatform",
    "api/src/FoxCMS/Api/Bootstrap/Runtime/RuntimeFilesystem.php": "final class RuntimeFilesystem",
    "api/src/FoxCMS/Api/Bootstrap/Runtime/RuntimeMetadata.php": "final class RuntimeMetadata",
    "api/src/FoxCMS/Api/Bootstrap/Runtime/RuntimeArchive.php": "final class RuntimeArchive",
    "api/src/FoxCMS/Api/Bootstrap/Runtime/RuntimeZip.php": "final class RuntimeZip",
    "api/src/FoxCMS/Api/Bootstrap/Runtime/RuntimeTar.php": "final class RuntimeTar",
    "api/src/FoxCMS/Api/Bootstrap/Runtime/RuntimeSelection.php": "final class RuntimeSelection",
    "api/src/FoxCMS/Api/Bootstrap/Runtime/RuntimeResolver.php": "final class RuntimeResolver",
    "scripts/diagnose-runtime.php": "FoxCMS runtime diagnostics",
    "scripts/migrate.php": "MigrationRunner",
    "scripts/migrate-user-storage.php": "Uuid::compact",
    "scripts/verify-bootstrap-catalog.py": "def validate_catalog",
    "scripts/publish-bootstrap-production.sh": "filesystem catalog",
    "database/schema-000.sql": "contains no accounts",
    "database/migrations/001_create_anti_brute.sql": "CREATE TABLE IF NOT EXISTS `antiBrute`",
    "database/migrations/002_harden_launcher_sessions.sql": "expiresAt",
    "database/migrations/003_uuid_user_identity.sql": "ux_users_uuid",
    "database/migrations/004_repair_legacy_schema.sql": "SELECT `serversOnline` FROM `users` LIMIT 0",
    "database/migrations/005_enforce_profile_runtime_fields.sql": "SELECT `balance`, `badges`, `serversOnline` FROM `users` LIMIT 0",
    "database/migrations/010_badge_claim_keys.sql": "`tokenHash` CHAR(64)",
    "database/migrations/014_rules_expert_claim_key.sql": "INSERT INTO `badgeClaimKeys`",
    "database/migrations/015_consolidate_user_badges.sql": "DROP TABLE IF EXISTS `userBadges`",
    "database/migrations/016_revoke_public_badge_claim_key.sql": "activeLegacyPublicBadgeKeys",
    "database/migrations/017_public_badge_claim_access.sql": "`accessMode` = 'public'",
    "database/migrations/018_expand_server_image_column.sql": "`serverImage` VARCHAR(512)",
    "database/migrations/025_game_achievements.sql": "CREATE TABLE IF NOT EXISTS `gameAchievements`",
    "database/repair-legacy-schema.sql": "Safe to run repeatedly",
}

STATIC_FORBIDDEN_PATHS = (
    "db.sql",
    "frontend",
    "engine/init.php",
    "engine/initHelper.php",
    "engine/RequestHandler.class.php",
    "engine/classes/services/system-requests",
    "engine/classes/utils/inDirScanner",
    "engine/classes/utils/UserUpload",
    "engine/classes/modules/Smarty",
    "engine/classes/syslib/smarty",
    "engine/classes/syslib/database.improved",
    "engine/data/frontend.json",
)

FORBIDDEN_SIGNATURES = {
    "index.php": ("new init", "engine/init.php"),
    "engine/data/const.php": ("language/ru_ru.lang", "language' . DIRECTORY_SEPARATOR"),
    "engine/Application.class.php": ("AppShellRenderer", "/app/index.html"),
    "engine/classes/session/UserSession.class.php": ("md5($this->login", "USR_SUBFOLDER . $login"),
    "engine/classes/modules/AuthReg/AuthReg.class.php": ("userMd5", "passMd5"),
    "engine/SystemRequests.class.php": ("authenticatedLauncherLogin", "WHERE `login` = :login"),
    "engine/classes/services/RuntimeJdkCatalog.class.php": ("/api/bootstrap/runtime-catalog", "$runtimeCatalogLibrary"),
}


def sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as source:
        for chunk in iter(lambda: source.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def require_text(root: Path, relative_path: str, signature: str, failures: list[str]) -> None:
    path = root / relative_path
    if not path.is_file():
        failures.append(f"required file is missing: {relative_path}")
        return
    try:
        content = path.read_text(encoding="utf-8")
    except UnicodeDecodeError:
        failures.append(f"required text file is not UTF-8: {relative_path}")
        return
    if signature not in content:
        failures.append(f"required signature {signature!r} is missing from {relative_path}")


def verify_theme(root: Path, theme: str, failures: list[str]) -> list[str]:
    if re.fullmatch(r"[A-Za-z0-9][A-Za-z0-9_-]{0,63}", theme) is None:
        failures.append(f"invalid theme name: {theme!r}")
        return []

    theme_root = root / "templates" / theme
    manifest_path = theme_root / "theme.json"
    shell_path = theme_root / "index.html"
    runtime_root = theme_root / "assets" / "runtime"
    fingerprints: list[str] = []

    if (theme_root / "app").exists():
        failures.append(f"forbidden one-off theme directory exists: templates/{theme}/app")
    if not manifest_path.is_file():
        failures.append(f"theme manifest is missing: templates/{theme}/theme.json")
        return fingerprints

    try:
        manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
    except (UnicodeDecodeError, json.JSONDecodeError) as error:
        failures.append(f"theme manifest is invalid: {error}")
        return fingerprints

    if not isinstance(manifest, dict) or manifest.get("schema") != 1:
        failures.append("theme manifest schema must be 1")
    if manifest.get("name") != theme:
        failures.append(f"theme manifest name must equal selected theme: {theme}")

    frontend_relative = manifest.get("frontend", "frontend.json")
    if (
        not isinstance(frontend_relative, str)
        or frontend_relative.startswith("/")
        or ".." in Path(frontend_relative).parts
    ):
        failures.append(f"unsafe theme frontend manifest path: {frontend_relative!r}")
    else:
        frontend_path = theme_root / frontend_relative
        if not frontend_path.is_file():
            failures.append(f"theme frontend manifest is missing: templates/{theme}/{frontend_relative}")
        else:
            try:
                frontend = json.loads(frontend_path.read_text(encoding="utf-8"))
            except (UnicodeDecodeError, json.JSONDecodeError) as error:
                failures.append(f"theme frontend manifest is invalid: {error}")
            else:
                if not isinstance(frontend, dict) or frontend.get("schema") != 1:
                    failures.append("theme frontend manifest schema must be 1")
                if not isinstance(frontend.get("routes"), list):
                    failures.append("theme frontend manifest routes must be an array")
                fingerprints.append(f"templates/{theme}/{frontend_relative}")

    if not shell_path.is_file():
        failures.append(f"theme shell is missing: templates/{theme}/index.html")
    else:
        shell = shell_path.read_text(encoding="utf-8", errors="replace")
        for marker in (
            'id="foxescraft-bootstrap"',
            '<!-- foxescraft:styles -->',
            '<!-- foxescraft:scripts -->',
        ):
            if marker not in shell:
                failures.append(f"theme shell marker is missing: {marker}")

    assets = manifest.get("assets", {}) if isinstance(manifest, dict) else {}
    for kind in ("styles", "scripts"):
        values = assets.get(kind, []) if isinstance(assets, dict) else []
        if not isinstance(values, list):
            failures.append(f"theme assets.{kind} must be an array")
            continue
        for relative in values:
            if not isinstance(relative, str) or relative.startswith("/") or ".." in Path(relative).parts:
                failures.append(f"unsafe theme asset path: {relative!r}")
                continue
            path = theme_root / relative
            if not path.is_file():
                failures.append(f"theme runtime asset is missing: templates/{theme}/{relative}")
            else:
                fingerprints.append(f"templates/{theme}/{relative}")

    for required in (runtime_root / "theme.js", runtime_root / "theme.css"):
        if not required.is_file():
            failures.append(f"theme runtime entry is missing: {required.relative_to(root)}")

    page_content_root = theme_root / "pages" / "content"
    page_templates_root = theme_root / "pages" / "templates"
    required_page_documents = {
        page_content_root / "about.html": "data-project-page",
        page_content_root / "start.html": "data-project-page",
        page_templates_root / "StaticContent.tpl": '<fox-page-template id="static-content"',
        page_templates_root / "StartGame.tpl": '<fox-page-template id="start-game"',
        page_templates_root / "Achievements.tpl": '<fox-page-template id="achievements"',
    }
    user_options_root = theme_root / "userOptions"
    required_user_options_documents = {
        user_options_root / "ProfileSettings.tpl": '<fox-user-options-template id="profile-settings"',
        user_options_root / "AdminPanel.tpl": '<fox-user-options-template id="admin-panel"',
    }
    for required, signature in required_page_documents.items():
        relative = str(required.relative_to(root)).replace("\\", "/")
        if not required.is_file():
            failures.append(f"theme page document is missing: {relative}")
            continue
        source = required.read_text(encoding="utf-8", errors="replace")
        if signature not in source:
            failures.append(f"theme page document signature {signature!r} is missing from {relative}")
        fingerprints.append(relative)

    user_option_sources: dict[str, str] = {}
    for required, signature in required_user_options_documents.items():
        relative = str(required.relative_to(root)).replace("\\", "/")
        if not required.is_file():
            failures.append(f"theme userOptions document is missing: {relative}")
            continue
        source = required.read_text(encoding="utf-8", errors="replace")
        if signature not in source:
            failures.append(f"theme userOptions signature {signature!r} is missing from {relative}")
        user_option_sources[required.stem] = source
        fingerprints.append(relative)

    repository_path = root / "engine" / "classes" / "themes" / "ThemeUserOptionsRepository.class.php"
    admin_source = user_option_sources.get("AdminPanel")
    if repository_path.is_file() and admin_source is not None:
        repository_source = repository_path.read_text(encoding="utf-8", errors="replace")
        adapters_match = re.search(
            r"private const ADMIN_ADAPTERS = \[(.*?)\n\s*\];",
            repository_source,
            re.DOTALL,
        )
        if adapters_match is None:
            failures.append("unable to inspect ThemeUserOptionsRepository ADMIN_ADAPTERS")
        else:
            adapter_ids = set(re.findall(r"^\s*'([^']+)'\s*=>", adapters_match.group(1), re.MULTILINE))
            tool_ids = set(re.findall(r'<fox-admin-tool\b[^>]*\bid="([^"]+)"', admin_source))
            if adapter_ids != tool_ids:
                missing = sorted(adapter_ids - tool_ids)
                extra = sorted(tool_ids - adapter_ids)
                failures.append(
                    "AdminPanel.tpl adapter set does not match backend ADMIN_ADAPTERS"
                    + (f"; missing={missing}" if missing else "")
                    + (f"; extra={extra}" if extra else "")
                )

    obsolete_page_paths = (
        theme_root / "data" / "pages",
        theme_root / "data" / "pages.json",
    )
    for obsolete in obsolete_page_paths:
        if obsolete.exists():
            failures.append(
                "obsolete split page storage exists: "
                + str(obsolete.relative_to(root)).replace("\\", "/")
            )

    runtime_templates_root = runtime_root / "templates"
    for template_id in ("static-content", "start-game", "achievements"):
        if not any(runtime_templates_root.glob(f"{template_id}.*.js")):
            failures.append(f"runtime page render module is missing: {template_id}.<revision>.js")

    for stem, template_id in (("ProfileSettings", "profile-settings"), ("AdminPanel", "admin-panel")):
        source = user_option_sources.get(stem)
        if source is None:
            continue
        revision_match = re.search(r'\brevision="(\d+)"', source)
        if revision_match is None:
            failures.append(f"runtime userOptions TPL revision is missing: {template_id}")
            continue
        module = runtime_templates_root / f"{template_id}.{revision_match.group(1)}.js"
        if not module.is_file():
            failures.append(f"runtime userOptions render module is missing: {module.name}")
        else:
            fingerprints.append(str(module.relative_to(root)).replace("\\", "/"))

    return fingerprints


def verify_uuid_runtime(root: Path, failures: list[str]) -> None:
    php_files = [
        path
        for path in root.rglob("*.php")
        if ".bak-" not in path.name
        and not any(part in {"node_modules", "assets", "cache"} for part in path.parts)
    ]
    for path in php_files:
        relative = str(path.relative_to(root)).replace("\\", "/")
        text = path.read_text(encoding="utf-8", errors="replace")
        if re.search(r"\b(?:userMd5|passMd5|userLogin)\b", text):
            failures.append(f"legacy identity column remains in runtime PHP: {relative}")
        if re.search(
            r"(?:UPDATE|DELETE)\s+`?users`?[\s\S]{0,500}?WHERE\s+`?(?:login|user_id)`?\s*=",
            text,
            re.IGNORECASE,
        ):
            failures.append(f"user mutation is not UUID-bound: {relative}")


def verify(root: Path, theme: str) -> tuple[list[str], list[str]]:
    failures: list[str] = []
    fingerprint_paths: list[str] = []
    if not root.is_dir():
        return [f"deployment root does not exist: {root}"], fingerprint_paths

    for relative_path, required_signature in STATIC_REQUIRED_FILES.items():
        require_text(root, relative_path, required_signature, failures)
        if (root / relative_path).is_file():
            fingerprint_paths.append(relative_path)

    for relative_path in STATIC_FORBIDDEN_PATHS:
        if (root / relative_path).exists():
            failures.append(f"removed or unsafe path still exists: {relative_path}")

    modules_root = root / "engine" / "classes" / "modules"
    if modules_root.is_dir():
        for module_frontend in modules_root.glob("*/frontend.json"):
            failures.append(
                "backend module must not own template routes: "
                + str(module_frontend.relative_to(root)).replace("\\", "/")
            )

    for relative_path, signatures in FORBIDDEN_SIGNATURES.items():
        path = root / relative_path
        if not path.is_file():
            continue
        content = path.read_text(encoding="utf-8", errors="replace")
        for signature in signatures:
            if signature in content:
                failures.append(f"legacy signature {signature!r} remains in {relative_path}")

    verify_uuid_runtime(root, failures)
    fingerprint_paths.extend(verify_theme(root, theme, failures))
    return failures, fingerprint_paths


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument(
        "root",
        nargs="?",
        default=Path(__file__).resolve().parents[1],
        type=Path,
        help="FoxCMS deployment root",
    )
    parser.add_argument(
        "--theme",
        default=os.getenv("FOXESCRAFT_TEMPLATE", "foxengine2"),
        help="selected directory under templates/",
    )
    args = parser.parse_args()
    root = args.root.resolve()

    failures, fingerprint_paths = verify(root, args.theme)
    if failures:
        print(f"FoxCMS deployment verification failed for {root}:", file=sys.stderr)
        for failure in failures:
            print(f"- {failure}", file=sys.stderr)
        return 1

    fingerprints = [
        f"{relative_path}={sha256(root / relative_path)[:16]}"
        for relative_path in dict.fromkeys(fingerprint_paths)
    ]
    print(f"FoxCMS deployment verified: {root}")
    print(f"Theme: {args.theme} (theme-owned frontend registry + UUID-bound engine runtime)")
    print("Fingerprint: " + ", ".join(fingerprints))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
