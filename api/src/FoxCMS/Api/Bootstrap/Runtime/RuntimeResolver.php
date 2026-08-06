<?php

declare(strict_types=1);

namespace FoxCMS\Api\Bootstrap\Runtime;

use Throwable;

/** Runtime catalog orchestration and API response assembly. */

final class RuntimeResolver
{
    public static function resolveRuntimeForRequest(string $storageDirectory, array $request): array
    {
        $runtimeRoot = rtrim($storageDirectory, '/\\') . DIRECTORY_SEPARATOR . 'runtime';
        if (!is_dir($runtimeRoot) || !is_readable($runtimeRoot)) {
            RuntimeSupport::fail(503, 'runtime_catalog_unavailable', 'The runtime catalog is unavailable.', array(
                'runtime_root' => 'runtime',
                'request' => $request,
            ));
        }

        $scanRoots = RuntimePlatform::runtimeCatalogScanRoots($runtimeRoot, $request['platform']);
        if (count($scanRoots) === 0) {
            RuntimeSupport::fail(503, 'runtime_platform_catalog_unavailable', 'No readable runtime branch exists for the requested platform.', array(
                'platform' => $request['platform'],
                'accepted_branches' => array_map(static function (array $segments): string {
                    return implode('/', $segments);
                }, RuntimePlatform::runtimeCatalogBranchesForPlatform($request['platform'])),
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
            foreach (RuntimeFilesystem::runtimeArchiveFiles($scanRoot) as $absolutePath) {
                ++$diagnostics['scanned_archives'];
                $relativeToStorage = RuntimeFilesystem::runtimeCatalogRelativePath($storageDirectory, $absolutePath);
                try {
                    $candidate = RuntimeArchive::inspectRuntimeArchive(
                        $absolutePath,
                        $relativeToStorage,
                        $request['platform'],
                        $branch
                    );
                    ++$diagnostics['inspected_archives'];

                    $compatibility = RuntimeSelection::evaluateRuntimeCompatibility($candidate, $request);
                    if (!$compatibility['compatible']) {
                        $diagnostics['rejected_candidates'][] = array(
                            'path' => $relativeToStorage,
                            'code' => $compatibility['code'],
                            'reason' => $compatibility['reason'],
                            'detected' => RuntimeSelection::summarizeRuntimeCandidate($candidate),
                        );
                        continue;
                    }

                    $candidate['score'] = RuntimeSelection::scoreRuntimeCandidate($candidate, $request);
                    $target = $candidate['install_path'];
                    if (isset($candidatesByTarget[$target])) {
                        $kept = RuntimeSelection::preferredRuntimeCandidate($candidatesByTarget[$target], $candidate);
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
            $majorMode = ($request['version_mode'] ?? 'exact') === 'major';
            RuntimeSupport::fail(404, $majorMode ? 'runtime_major_version_unavailable' : 'runtime_exact_version_unavailable', sprintf(
                $majorMode
                    ? 'No compatible Java runtime was found for %s and Java major %s.'
                    : 'No compatible Java runtime was found for %s and exact version %s.',
                $request['platform'],
                $request['version']
            ), $diagnostics);
        }

        usort($candidates, static function (array $left, array $right) use ($request): int {
            if (($request['version_mode'] ?? 'exact') === 'major') {
                $versionOrder = version_compare((string)$right['version_core'], (string)$left['version_core']);
                if ($versionOrder !== 0) {
                    return $versionOrder;
                }
            }
            if ($left['score'] !== $right['score']) {
                return $right['score'] <=> $left['score'];
            }
            return strnatcasecmp($left['path'], $right['path']);
        });

        $selected = $candidates[0];
        $artifact = RuntimeSupport::describeCatalogFile($storageDirectory, $selected['absolute_path']);
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
                    'version_mode' => $request['version_mode'] ?? 'exact',
                    'client_version' => $request['client_version'],
                ),
                'scanned_archives' => $diagnostics['scanned_archives'],
                'compatible_archives' => $diagnostics['compatible_archives'],
                'selected' => RuntimeSelection::summarizeRuntimeCandidate($selected),
                'reason' => RuntimeSelection::buildRuntimeSelectionReason($selected, $request),
            ),
            'artifacts' => array($request['platform'] => $descriptor),
        );
    }
}
