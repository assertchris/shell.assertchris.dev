<?php

namespace App\Exceptions\Jira;

use RuntimeException;

class JiraClientException extends RuntimeException
{
    public function __construct(public readonly int $statusCode, string $message)
    {
        parent::__construct($message);
    }
}
