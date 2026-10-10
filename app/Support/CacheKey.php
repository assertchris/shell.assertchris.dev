<?php

namespace App\Support;

class CacheKey
{
    public static function for(string|int ...$parts): CacheOperation
    {
        return new CacheOperation(implode(':', $parts));
    }

    public static function hashed(array $params, string|int ...$prefix): CacheOperation
    {
        ksort($params);

        return new CacheOperation(implode(':', [...$prefix, md5(serialize($params))]));
    }
}
