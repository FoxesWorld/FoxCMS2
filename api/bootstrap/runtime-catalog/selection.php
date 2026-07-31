<?php

declare(strict_types=1);

/** Exact-version candidate compatibility, ranking and diagnostics. */

function evaluateRuntimeCompatibility(array $candidate, array $request): array
{
    if ($candidate['platform'] !== $request['platform']) {
        return array('compatible' => false, 'code' => 'platform_mismatch', 'reason' => sprintf(
            'Detected platform %s, requested %s.',
            $candidate['platform'],
            $request['platform']
        ));
    }
    if ($candidate['version_core'] !== $request['version']) {
        return array('compatible' => false, 'code' => 'java_version_not_exact', 'reason' => sprintf(
            'Detected Java %s, exact Java %s was requested.',
            $candidate['version_core'],
            $request['version']
        ));
    }
    if ($request['distribution'] !== 'any' && $candidate['distribution'] !== $request['distribution']) {
        return array('compatible' => false, 'code' => 'distribution_mismatch', 'reason' => sprintf(
            'Detected %s, requested %s.',
            $candidate['distribution'],
            $request['distribution']
        ));
    }
    if (!$request['allow_prerelease'] && !$candidate['stable']) {
        return array('compatible' => false, 'code' => 'prerelease_rejected', 'reason' => 'Prerelease runtimes are disabled.');
    }
    return array('compatible' => true, 'code' => 'compatible', 'reason' => 'Exact version and platform match.');
}
function scoreRuntimeCandidate(array $candidate, array $request): int
{
    $score = 1000;
    $score += $candidate['inspection'] === 'zip-metadata' || $candidate['inspection'] === 'tar-metadata' ? 200 : 0;
    $score += $candidate['stable'] ? 100 : 0;
    if ($request['distribution'] === 'any') {
        $score += $candidate['distribution'] === 'jdk' ? 40 : 30;
    } elseif ($candidate['distribution'] === $request['distribution']) {
        $score += 80;
    }
    if ($request['vendor'] !== '' && stripos($candidate['vendor'], $request['vendor']) !== false) {
        $score += 120;
    }
    $score += max(0, 20 - substr_count($candidate['path'], '/'));
    return $score;
}
function preferredRuntimeCandidate(array $left, array $right): array
{
    if ($left['score'] !== $right['score']) {
        return $left['score'] > $right['score'] ? $left : $right;
    }
    return strnatcasecmp($left['path'], $right['path']) <= 0 ? $left : $right;
}
function summarizeRuntimeCandidate(array $candidate): array
{
    return array(
        'runtime_id' => $candidate['runtime_id'],
        'path' => $candidate['path'],
        'catalog_branch' => $candidate['catalog_branch'],
        'platform' => $candidate['platform'],
        'version' => $candidate['version'],
        'java_major' => $candidate['java_major'],
        'vendor' => $candidate['vendor'],
        'distribution' => $candidate['distribution'],
        'name' => $candidate['name'],
        'install_path' => $candidate['install_path'],
        'inspection' => $candidate['inspection'],
        'score' => isset($candidate['score']) ? $candidate['score'] : null,
    );
}
function buildRuntimeSelectionReason(array $selected, array $request): string
{
    return sprintf(
        'Selected exact Java %s from %s: %s %s (%s), target=%s.',
        $request['version'],
        $selected['catalog_branch'],
        $selected['vendor'],
        $selected['version'],
        strtoupper($selected['distribution']),
        $selected['install_path']
    );
}
