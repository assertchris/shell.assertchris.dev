<?php

use AC\LaravelTaggedCache\TaggedCache;
use App\Models\Account;
use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('cache.default', 'array');
});

// Account cache

test('account resolveRouteBinding caches and second call skips DB', function () {
    $account = Account::factory()->create();

    $model = new Account;

    // Warm the cache
    $model->resolveRouteBinding($account->id);

    expect(TaggedCache::for(tags: ['accounts'], context: ['id' => $account->id])->get())->not->toBeNull();

    DB::enableQueryLog();

    $model->resolveRouteBinding($account->id);

    $queries = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn ($q) => str_contains($q, 'accounts'))
        ->values();

    expect($queries)->toBeEmpty();

    DB::disableQueryLog();
});

// User cache

test('user findCached caches and second call skips DB', function () {
    $user = User::factory()->create();

    // Warm the cache
    User::findCached($user->id);

    expect(TaggedCache::for(tags: ['users'], context: ['id' => $user->id])->get())->not->toBeNull();

    DB::enableQueryLog();

    User::findCached($user->id);

    $queries = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn ($q) => str_contains($q, 'users'))
        ->values();

    expect($queries)->toBeEmpty();

    DB::disableQueryLog();
});

// Token cache

test('token findToken caches and second call skips DB', function () {
    $user = User::factory()->create();
    $tokenResult = $user->createToken('test-token');
    $rawToken = explode('|', $tokenResult->plainTextToken)[1];
    $hash = hash('sha256', $rawToken);

    // Warm the cache
    PersonalAccessToken::findToken($tokenResult->plainTextToken);

    expect(TaggedCache::for(tags: ['tokens'], context: ['hash' => $hash])->get())->not->toBeNull();

    DB::enableQueryLog();

    PersonalAccessToken::findToken($tokenResult->plainTextToken);

    $queries = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn ($q) => str_contains($q, 'personal_access_tokens'))
        ->values();

    expect($queries)->toBeEmpty();

    DB::disableQueryLog();
});

// AccountObserver

test('AccountObserver saved flushes single-account cache entry', function () {
    $account = Account::factory()->create();

    TaggedCache::for(tags: ['accounts'], context: ['id' => $account->id])
        ->put($account, now()->addDays(7));

    expect(TaggedCache::for(tags: ['accounts'], context: ['id' => $account->id])->get())->not->toBeNull();

    $account->name = 'Updated';
    $account->save();

    expect(TaggedCache::for(tags: ['accounts'], context: ['id' => $account->id])->get())->toBeNull();
});

test('AccountObserver saved flushes accounts-list cache entry via account:user tag', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id]);

    TaggedCache::for(tags: ["account:user:{$user->id}"], context: ['page' => 1])
        ->put(['list' => 'of accounts'], now()->addDays(7));

    expect(TaggedCache::for(tags: ["account:user:{$user->id}"], context: ['page' => 1])->get())->not->toBeNull();

    $account->name = 'Updated';
    $account->save();

    expect(TaggedCache::for(tags: ["account:user:{$user->id}"], context: ['page' => 1])->get())->toBeNull();
});

test('AccountObserver flushes old and new user tags when user_id changes', function () {
    $originalUser = User::factory()->create();
    $newUser = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $originalUser->id]);

    // Seed entries for both user tags
    TaggedCache::for(tags: ["account:user:{$originalUser->id}"], context: ['page' => 1])
        ->put(['list' => 'original'], now()->addDays(7));
    TaggedCache::for(tags: ["account:user:{$newUser->id}"], context: ['page' => 1])
        ->put(['list' => 'new'], now()->addDays(7));

    expect(TaggedCache::for(tags: ["account:user:{$originalUser->id}"], context: ['page' => 1])->get())->not->toBeNull();
    expect(TaggedCache::for(tags: ["account:user:{$newUser->id}"], context: ['page' => 1])->get())->not->toBeNull();

    $account->user_id = $newUser->id;
    $account->save();

    expect(TaggedCache::for(tags: ["account:user:{$originalUser->id}"], context: ['page' => 1])->get())->toBeNull();
    expect(TaggedCache::for(tags: ["account:user:{$newUser->id}"], context: ['page' => 1])->get())->toBeNull();
});

// PersonalAccessTokenObserver

test('token saving hook patches last_used_at in cache and returns false to skip DB write', function () {
    $user = User::factory()->create();
    $tokenResult = $user->createToken('test-token');
    $rawToken = explode('|', $tokenResult->plainTextToken)[1];
    $hash = hash('sha256', $rawToken);

    // Warm the cache
    PersonalAccessToken::findToken($tokenResult->plainTextToken);

    $pat = PersonalAccessToken::where('token', $hash)->first();

    $newLastUsedAt = now()->addMinute();

    DB::enableQueryLog();

    $pat->forceFill(['last_used_at' => $newLastUsedAt])->save();

    $queries = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn ($q) => str_contains(strtolower($q), 'update') && str_contains($q, 'personal_access_tokens'))
        ->values();

    expect($queries)->toBeEmpty();

    $cached = TaggedCache::for(tags: ['tokens'], context: ['hash' => $hash])->get();
    expect($cached)->not->toBeNull();
    expect($cached->last_used_at->toDateTimeString())->toBe($newLastUsedAt->toDateTimeString());

    DB::disableQueryLog();
});

test('token saved hook clears cache entry', function () {
    $user = User::factory()->create();
    $tokenResult = $user->createToken('test-token');
    $rawToken = explode('|', $tokenResult->plainTextToken)[1];
    $hash = hash('sha256', $rawToken);

    // Warm the cache
    PersonalAccessToken::findToken($tokenResult->plainTextToken);

    expect(TaggedCache::for(tags: ['tokens'], context: ['hash' => $hash])->get())->not->toBeNull();

    $pat = PersonalAccessToken::where('token', $hash)->first();
    $pat->forceFill(['last_ip_address' => '9.9.9.9'])->save();

    expect(TaggedCache::for(tags: ['tokens'], context: ['hash' => $hash])->get())->toBeNull();
});

test('token deleted hook clears cache entry', function () {
    $user = User::factory()->create();
    $tokenResult = $user->createToken('test-token');
    $rawToken = explode('|', $tokenResult->plainTextToken)[1];
    $hash = hash('sha256', $rawToken);

    // Warm the cache
    PersonalAccessToken::findToken($tokenResult->plainTextToken);

    expect(TaggedCache::for(tags: ['tokens'], context: ['hash' => $hash])->get())->not->toBeNull();

    $pat = PersonalAccessToken::where('token', $hash)->first();
    $pat->delete();

    expect(TaggedCache::for(tags: ['tokens'], context: ['hash' => $hash])->get())->toBeNull();
});
