<?php

use App\Models\User;
use App\Support\CacheKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('findCached caches the user and second call skips DB', function () {
    $user = User::factory()->create();

    // Warm the cache
    User::findCached($user->id);

    expect(Cache::has((string) CacheKey::for('user', $user->id)))->toBeTrue();

    DB::enableQueryLog();

    User::findCached($user->id);

    $queries = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn ($q) => str_contains($q, 'users'))
        ->values();

    expect($queries)->toBeEmpty();

    DB::disableQueryLog();
});

test('saved observer busts the user cache', function () {
    $user = User::factory()->create();

    // Warm the cache
    User::findCached($user->id);

    expect(Cache::has((string) CacheKey::for('user', $user->id)))->toBeTrue();

    $user->name = 'Updated Name';
    $user->save();

    expect(Cache::has((string) CacheKey::for('user', $user->id)))->toBeFalse();
});
