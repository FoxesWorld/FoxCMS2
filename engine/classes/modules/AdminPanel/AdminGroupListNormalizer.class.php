<?php

declare(strict_types=1);

/**
 * Converts mixed administrative group input to canonical, existing group tags.
 */
final class AdminGroupListNormalizer
{
    public function __construct(private GroupRepository $groups)
    {
    }

    /** @return list<string> */
    public function normalize(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $source = is_array($decoded) ? $decoded : explode(',', $value);
        } elseif (is_array($value)) {
            $source = $value;
        } else {
            $source = [];
        }

        $tags = [];
        foreach ($source as $group) {
            $tag = $this->groups->resolveTag($group, '');
            if ($tag !== '' && $this->groups->exists($tag)) {
                $tags[] = $tag;
            }
        }
        $tags = array_values(array_unique($tags));
        sort($tags, SORT_STRING);
        return $tags;
    }
}
