<?php

declare(strict_types=1);

final class UserTextureLocator
{
    public function __construct(private string $usersRoot)
    {
    }

    /** @return array{skin:string,cape:string} */
    public function locate(string $userUuid): array
    {
        $canonical = Uuid::canonical($userUuid);
        $compact = Uuid::compact($userUuid);
        $canonicalFolder = rtrim($this->usersRoot, '/\\') . DIRECTORY_SEPARATOR
            . $canonical . DIRECTORY_SEPARATOR;
        $compactFolder = rtrim($this->usersRoot, '/\\') . DIRECTORY_SEPARATOR
            . $compact . DIRECTORY_SEPARATOR;

        $skinCandidates = [
            $canonicalFolder . $canonical . '-skin.png',
            $canonicalFolder . $compact . '-skin.png',
            $compactFolder . $canonical . '-skin.png',
            $compactFolder . $compact . '-skin.png',
        ];
        $capeCandidates = [
            $canonicalFolder . $canonical . '-cape.png',
            $canonicalFolder . $compact . '-cape.png',
            $compactFolder . $canonical . '-cape.png',
            $compactFolder . $compact . '-cape.png',
        ];

        return [
            'skin' => $this->firstExisting($skinCandidates) ?? $skinCandidates[0],
            'cape' => $this->firstExisting($capeCandidates) ?? $capeCandidates[0],
        ];
    }

    /** @param list<string> $paths */
    private function firstExisting(array $paths): ?string
    {
        foreach ($paths as $path) {
            if (is_file($path) && !is_link($path)) {
                return $path;
            }
        }
        return null;
    }
}
