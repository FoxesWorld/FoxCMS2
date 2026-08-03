<?php

declare(strict_types=1);

final class ThemeEmoticonRepository
{
    private const MAXIMUM_MANIFEST_BYTES = 131_072;
    private const MAXIMUM_IMAGE_BYTES = 2_097_152;

    private string $directory;
    private string $manifestPath;
    private string $publicBase;

    public function __construct(string $templatesDirectory, string $themeName)
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/D', $themeName) !== 1) {
            throw new InvalidArgumentException('Invalid theme name for emoticon catalog.');
        }

        $templatesRoot = realpath($templatesDirectory);
        $themeDirectory = is_string($templatesRoot)
            ? realpath($templatesRoot . DIRECTORY_SEPARATOR . $themeName)
            : false;
        if (!is_string($templatesRoot) || !is_string($themeDirectory) || !is_dir($themeDirectory)
            || !str_starts_with($themeDirectory, rtrim($templatesRoot, '/\\') . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Theme directory is unavailable for emoticon catalog.');
        }

        $directory = $themeDirectory . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'emoticons';
        if (!is_dir($directory) || is_link($directory)) {
            throw new RuntimeException('Theme emoticon directory is unavailable.');
        }

        $this->directory = $directory;
        $this->manifestPath = $directory . DIRECTORY_SEPARATOR . 'emoji.json';
        $this->publicBase = '/templates/' . rawurlencode($themeName) . '/data/emoticons/';
    }

    /**
     * @return array{
     *   schema:int,
     *   syntax:string,
     *   count:int,
     *   categories:list<array{
     *     id:string,
     *     label:string,
     *     items:list<array{name:string,shortcode:string,url:string,width:int,height:int}>
     *   }>
     * }
     */
    public function catalog(): array
    {
        if (!is_file($this->manifestPath) || is_link($this->manifestPath)) {
            throw new RuntimeException('Theme emoticon manifest is unavailable.');
        }
        $size = filesize($this->manifestPath);
        if (!is_int($size) || $size < 2 || $size > self::MAXIMUM_MANIFEST_BYTES) {
            throw new RuntimeException('Theme emoticon manifest has an invalid size.');
        }
        $json = file_get_contents($this->manifestPath);
        if (!is_string($json)) {
            throw new RuntimeException('Unable to read theme emoticon manifest.');
        }
        $manifest = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($manifest) || array_is_list($manifest) || ($manifest['schema'] ?? null) !== 1) {
            throw new UnexpectedValueException('Unsupported theme emoticon manifest schema.');
        }
        $sourceCategories = $manifest['categories'] ?? null;
        if (!is_array($sourceCategories) || !array_is_list($sourceCategories) || $sourceCategories === []) {
            throw new UnexpectedValueException('Theme emoticon manifest must define categories.');
        }

        $categories = [];
        $categoryIds = [];
        $names = [];
        $count = 0;
        foreach ($sourceCategories as $sourceCategory) {
            if (!is_array($sourceCategory) || array_is_list($sourceCategory)) {
                throw new UnexpectedValueException('Invalid emoticon category entry.');
            }
            $categoryId = trim((string)($sourceCategory['id'] ?? ''));
            if (preg_match('/^[a-z][a-z0-9_]{0,47}$/D', $categoryId) !== 1 || isset($categoryIds[$categoryId])) {
                throw new UnexpectedValueException('Invalid or duplicate emoticon category: ' . $categoryId);
            }
            $categoryIds[$categoryId] = true;
            $label = trim((string)($sourceCategory['label'] ?? ''));
            if ($label === '' || $this->unicodeLength($label) > 80 || preg_match('//u', $label) !== 1) {
                throw new UnexpectedValueException('Invalid emoticon category label: ' . $categoryId);
            }
            $sourceItems = $sourceCategory['items'] ?? null;
            if (!is_array($sourceItems) || !array_is_list($sourceItems) || $sourceItems === []) {
                throw new UnexpectedValueException('Emoticon category cannot be empty: ' . $categoryId);
            }

            $items = [];
            foreach ($sourceItems as $sourceItem) {
                if (!is_array($sourceItem) || array_is_list($sourceItem)) {
                    throw new UnexpectedValueException('Invalid emoticon entry in category: ' . $categoryId);
                }
                $name = trim((string)($sourceItem['name'] ?? ''));
                if (preg_match('/^[A-Za-z][A-Za-z0-9_-]{0,47}$/D', $name) !== 1) {
                    throw new UnexpectedValueException('Invalid emoticon name: ' . $name);
                }
                $nameKey = strtolower($name);
                if (isset($names[$nameKey])) {
                    throw new UnexpectedValueException('Duplicate emoticon name: ' . $name);
                }
                $names[$nameKey] = true;

                $relativePath = $categoryId . DIRECTORY_SEPARATOR . $name . '.png';
                $path = realpath($this->directory . DIRECTORY_SEPARATOR . $relativePath);
                $categoryDirectory = realpath($this->directory . DIRECTORY_SEPARATOR . $categoryId);
                if (!is_string($path) || !is_string($categoryDirectory) || !is_file($path) || is_link($path)
                    || !str_starts_with($path, rtrim($categoryDirectory, '/\\') . DIRECTORY_SEPARATOR)) {
                    throw new RuntimeException('Emoticon image is unavailable: ' . $categoryId . '/' . $name . '.png');
                }
                $imageSize = filesize($path);
                if (!is_int($imageSize) || $imageSize < 1 || $imageSize > self::MAXIMUM_IMAGE_BYTES) {
                    throw new RuntimeException('Emoticon image has an invalid size: ' . $name);
                }
                $dimensions = getimagesize($path);
                if (!is_array($dimensions) || ($dimensions[2] ?? null) !== IMAGETYPE_PNG) {
                    throw new RuntimeException('Emoticon image must be a valid PNG: ' . $name);
                }

                $items[] = [
                    'name' => $name,
                    'shortcode' => ':' . $name . ':',
                    'url' => $this->publicBase . rawurlencode($categoryId) . '/' . rawurlencode($name) . '.png',
                    'width' => (int)$dimensions[0],
                    'height' => (int)$dimensions[1],
                ];
                $count++;
            }

            $categories[] = ['id' => $categoryId, 'label' => $label, 'items' => $items];
        }

        return [
            'schema' => 1,
            'syntax' => ':emoji:',
            'count' => $count,
            'categories' => $categories,
        ];
    }
    private function unicodeLength(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($value, 'UTF-8');
        }
        $count = preg_match_all('/./us', $value, $matches);
        return is_int($count) ? $count : strlen($value);
    }

}
