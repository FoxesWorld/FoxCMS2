<?php

declare(strict_types=1);

final class ArtifactRepository
{
    public function __construct(
        private string $projectRoot,
        private string $uploadsRoot,
    ) {
    }

    /**
     * @param list<string> $extensions
     * @return array{filename:string,hash:string,sha256:string,size:int}
     */
    public function latest(string $relativeDirectory, array $extensions): array
    {
        $extensions = array_values(array_unique(array_filter(array_map(
            static fn (mixed $extension): string => strtolower(trim((string)$extension)),
            $extensions,
        ))));
        if ($extensions === []) {
            throw new InvalidArgumentException('At least one artifact extension is required.');
        }

        $root = realpath($this->projectRoot);
        $uploads = realpath($this->uploadsRoot);
        $directory = realpath(
            rtrim($this->projectRoot, '/\\') . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, trim($relativeDirectory, '/')),
        );
        if (!is_string($root) || !is_string($uploads) || !is_string($directory)
            || !is_dir($directory) || !$this->inside($directory, $uploads)) {
            throw new HttpException('Artifact directory not found.', 404);
        }

        $files = [];
        foreach (new DirectoryIterator($directory) as $entry) {
            if (!$entry->isFile() || $entry->isLink()) {
                continue;
            }
            if (!in_array(strtolower($entry->getExtension()), $extensions, true)) {
                continue;
            }
            $path = $entry->getRealPath();
            if (is_string($path) && $this->inside($path, $uploads)) {
                $files[] = $path;
            }
        }
        if ($files === []) {
            throw new HttpException('Artifact not found.', 404);
        }

        usort($files, static function (string $left, string $right): int {
            $versionOrder = version_compare(
                pathinfo($right, PATHINFO_FILENAME),
                pathinfo($left, PATHINFO_FILENAME),
            );
            return $versionOrder !== 0 ? $versionOrder : strcmp($right, $left);
        });

        $file = $files[0];
        $md5 = hash_file('md5', $file);
        $sha256 = hash_file('sha256', $file);
        $size = filesize($file);
        if (!is_string($md5) || !is_string($sha256) || !is_int($size)) {
            throw new RuntimeException('Unable to fingerprint artifact.');
        }

        return [
            'filename' => str_replace('\\', '/', substr($file, strlen(rtrim($root, '/\\')) + 1)),
            'hash' => $md5,
            'sha256' => $sha256,
            'size' => $size,
        ];
    }

    private function inside(string $path, string $root): bool
    {
        $root = rtrim($root, '/\\');
        return $path === $root || str_starts_with($path, $root . DIRECTORY_SEPARATOR);
    }
}
