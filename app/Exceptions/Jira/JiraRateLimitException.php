<?php

namespace App\Exceptions\Jira;

use RuntimeException;

class JiraRateLimitException extends RuntimeException
{
    public function __construct(public readonly int $retryAfter)
    {
        parent::__construct("Jira rate limit exceeded. Retry after {$retryAfter} seconds.");
    }
}
