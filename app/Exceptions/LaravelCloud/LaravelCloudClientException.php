<?php

namespace App\Exceptions\LaravelCloud;

use RuntimeException;

class LaravelCloudClientException extends RuntimeException
{
    public function __construct(public readonly int $statusCode, string $message)
    {
        parent::__construct($message);
    }
}
