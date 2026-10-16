<?php

namespace App\Exceptions\LaravelCloud;

use RuntimeException;

class LaravelCloudRateLimitException extends RuntimeException
{
    public function __construct(public readonly int $retryAfter)
    {
        parent::__construct("Laravel Cloud rate limit exceeded. Retry after {$retryAfter} seconds.");
    }
}
