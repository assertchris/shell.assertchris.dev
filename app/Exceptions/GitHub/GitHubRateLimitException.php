<?php

namespace App\Exceptions\GitHub;

use RuntimeException;

class GitHubRateLimitException extends RuntimeException
{
    public function __construct(public readonly int $retryAfter)
    {
        parent::__construct("GitHub rate limit exceeded. Retry after {$retryAfter} seconds.");
    }
}
