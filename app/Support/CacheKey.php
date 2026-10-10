<?php

namespace App\Support;

class CacheKey
{
    public static function for(string|int ...$parts): string
    {
        return implode(':', $parts);
    }

    public static function hashed(array $params, string|int ...$prefix): string
    {
        ksort($params);

        return implode(':', [...$prefix, md5(serialize($params))]);
    }
}
