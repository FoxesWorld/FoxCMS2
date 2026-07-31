<?php

declare(strict_types=1);

/** Runtime catalog orchestration and API response assembly. */

function resolveRuntimeForRequest(string $storageDirectory): array
{
    $request = parseRuntimeRequest();
    $runtimeRoot = rtrim($storageDirectory, '/\\') . DIRECTORY_SEPARATOR . 'runtime';
    if (!is_dir($runtimeRoot) || !is_readable($runtimeRoot)) {
        fail(503, 'runtime_catalog_unavailable', 'The runtime catalog is unavailable.', array(
            'runtime_root' => 'runtime',
            'request' => $request,
        ));
    }

    $scanRoots = runtimeCatalogScanRoots($runtimeRoot, $request['platform']);
    if (count($scanRoots) === 0) {
        fail(503, 'runtime_platform_catalog_unavailable', 'No readable runtime branch exists for the requested platform.', array(
            'platform' => $request['platform'],
            'accepted_branches' => array_map(static function (array $segments): string {
                return implode('/', $segments);
            }, runtimeCatalogBranchesForPlatform($request['platform'])),
        ));
    }

    $diagnostics = array(
        'request' => $request,
        'branches' => array_keys($scanRoots),
        'scanned_archives' => 0,
        'inspected_archives' => 0,
        'compatible_archives' => 0,
        'rejected_candidates' => array(),
        'duplicate_targets' => array(),
    );

    $candidatesByTarget = array();
    foreach ($scanRoots as $branch => $scanRoot) {
        foreach (runtimeArchiveFiles($scanRoot) as $absolutePath) {
            ++$diagnostics['scanned_archives'];
            $relativeToStorage = runtimeCatalogRelativePath($storageDirectory, $absolutePath);
            try {
                $candidate = inspectRuntimeArchive(
                    $absolutePath,
                    $relativeToStorage,
                    $request['platform'],
                    $branch
                );
                ++$diagnostics['inspected_archives'];

                $compatibility = evaluateRuntimeCompatibility($candidate, $request);
                if (!$compatibility['compatible']) {
                    $diagnostics['rejected_candidates'][] = array(
                        'path' => $relativeToStorage,
                        'code' => $compatibility['code'],
                        'reason' => $compatibility['reason'],
                        'detected' => summarizeRuntimeCandidate($candidate),
                    );
                    continue;
                }

                $candidate['score'] = scoreRuntimeCandidate($candidate, $request);
                $target = $candidate['install_path'];
                if (isset($candidatesByTarget[$target])) {
                    $kept = preferredRuntimeCandidate($candidatesByTarget[$target], $candidate);
                    $dropped = $kept['path'] === $candidate['path']
                        ? $candidatesByTarget[$target]
                        : $candidate;
                    $candidatesByTarget[$target] = $kept;
                    $diagnostics['duplicate_targets'][] = array(
                        'install_path' => $target,
                        'kept' => $kept['path'],
                        'dropped' => $dropped['path'],
                    );
                    continue;
                }
                $candidatesByTarget[$target] = $candidate;
            } catch (Throwable $exception) {
                $diagnostics['rejected_candidates'][] = array(
                    'path' => $relativeToStorage,
                    'code' => 'archive_inspection_failed',
                    'reason' => $exception->getMessage(),
                    'exception' => get_class($exception),
                );
            }
        }
    }

    $candidates = array_values($candidatesByTarget);
    $diagnostics['compatible_archives'] = count($candidates);
    if (count($candidates) === 0) {
        fail(404, 'runtime_exact_version_unavailable', sprintf(
            'No compatible Java runtime was found for %s and exact version %s.',
            $request['platform'],
            $request['version']
        ), $diagnostics);
    }

    usort($candidates, static function (array $left, array $right): int {
        if ($left['score'] !== $right['score']) {
            return $right['score'] <=> $left['score'];
        }
        return strnatcasecmp($left['path'], $right['path']);
    });

    $selected = $candidates[0];
    $artifact = describeCatalogFile($storageDirectory, $selected['absolute_path']);
    $descriptor = array(
        'runtime_id' => $selected['runtime_id'],
        'url' => $artifact['url'],
        'sha256' => $artifact['sha256'],
        'size' => $artifact['size'],
        'name' => $selected['name'],
        'install_path' => $selected['install_path'],
        'java_path' => $selected['java_path'],
        'file_name' => $selected['file_name'],
        'archive' => $selected['archive'],
        'strip_components' => $selected['strip_components'],
        'vendor' => $selected['vendor'],
        'distribution' => $selected['distribution'],
        'version' => $selected['version'],
        'java_major' => $selected['java_major'],
        'platform' => $selected['platform'],
        'inspection' => $selected['inspection'],
    );

    return array(
        'version' => $selected['version'],
        'java_path' => $selected['java_path'],
        'selection' => array(
            'requested' => array(
                'platform' => $request['platform'],
                'version' => $request['version'],
                'client_version' => $request['client_version'],
            ),
            'scanned_archives' => $diagnostics['scanned_archives'],
            'compatible_archives' => $diagnostics['compatible_archives'],
            'selected' => summarizeRuntimeCandidate($selected),
            'reason' => buildRuntimeSelectionReason($selected, $request),
        ),
        'artifacts' => array($request['platform'] => $descriptor),
    );
}
