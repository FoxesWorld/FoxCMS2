<?php

declare(strict_types=1);

/**
 * FoxesCraft Java runtime catalog facade.
 *
 * The implementation is split by responsibility under runtime-catalog/ while
 * this file remains the stable include consumed by manifest.php.
 */

require_once __DIR__ . '/runtime-catalog/request.php';
require_once __DIR__ . '/runtime-catalog/platform.php';
require_once __DIR__ . '/runtime-catalog/filesystem.php';
require_once __DIR__ . '/runtime-catalog/metadata.php';
require_once __DIR__ . '/runtime-catalog/archive.php';
require_once __DIR__ . '/runtime-catalog/zip.php';
require_once __DIR__ . '/runtime-catalog/tar.php';
require_once __DIR__ . '/runtime-catalog/selection.php';
require_once __DIR__ . '/runtime-catalog/resolver.php';
