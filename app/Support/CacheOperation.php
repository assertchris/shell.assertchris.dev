<?php

namespace App\Support;

use Closure;
use DateInterval;
use DateTimeInterface;
use Illuminate\Support\Facades\Cache;
use Stringable;

class CacheOperation implements Stringable
{
    public function __construct(private string $key) {}

    public function __toString(): string
    {
        return $this->key;
    }

    public function remember(DateTimeInterface|DateInterval|int $ttl, Closure $fn): mixed
    {
        return Cache::remember($this->key, $ttl, $fn);
    }

    public function get(): mixed
    {
        return Cache::get($this->key);
    }

    public function put(mixed $value, DateTimeInterface|DateInterval|int $ttl): bool
    {
        return Cache::put($this->key, $value, $ttl);
    }

    public function forget(): void
    {
        Cache::forget($this->key);
    }
}
