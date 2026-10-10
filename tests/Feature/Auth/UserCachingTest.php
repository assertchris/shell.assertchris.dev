<?php

use AC\LaravelTaggedCache\TaggedCache;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('cache.default', 'array');
});

test('findCached caches the user and second call skips DB', function () {
    $user = User::factory()->create();

    // Warm the cache
    User::findCached($user->id);

    expect(TaggedCache::for(tags: ['users', "user:{$user->id}"], context: ['id' => $user->id])->get())->not->toBeNull();

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

    expect(TaggedCache::for(tags: ['users', "user:{$user->id}"], context: ['id' => $user->id])->get())->not->toBeNull();

    $user->name = 'Updated Name';
    $user->save();

    expect(TaggedCache::for(tags: ['users', "user:{$user->id}"], context: ['id' => $user->id])->get())->toBeNull();
});
