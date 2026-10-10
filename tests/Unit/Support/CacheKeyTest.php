<?php

use App\Support\CacheKey;

test('for() joins parts with colon', function (): void {
    expect((string) CacheKey::for('token', 123, 'abc'))->toBe('token:123:abc');
});

test('hashed() produces a stable hash regardless of param insertion order', function (): void {
    $a = CacheKey::hashed(['b' => 2, 'a' => 1], 'prefix');
    $b = CacheKey::hashed(['a' => 1, 'b' => 2], 'prefix');

    expect((string) $a)->toBe((string) $b);
});

test('hashed() with empty params produces a consistent, stable hash', function (): void {
    $first = CacheKey::hashed([], 'prefix');
    $second = CacheKey::hashed([], 'prefix');

    expect((string) $first)->toBe((string) $second);
});
