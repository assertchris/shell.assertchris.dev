<?php

use AC\LaravelTaggedCache\TaggedCache;
use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('cache.default', 'array');
});

test('route model binding serves account from cache after first request', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->withSlack()->create();
    $token = $user->createToken('test');

    // First request warms cache
    $this->getJson("/api/slack/{$account->id}/channels", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ]);

    expect(
        TaggedCache::for(tags: ['accounts', "account:{$account->id}"], context: ['id' => $account->id])->get()
    )->not->toBeNull();

    DB::enableQueryLog();

    // Second request uses cache for account binding
    $this->getJson("/api/slack/{$account->id}/channels", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ]);

    $queries = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn ($q) => str_contains($q, '"accounts"') && str_contains(strtolower($q), 'select'))
        ->values();

    expect($queries)->toBeEmpty();

    DB::disableQueryLog();
});

test('AccountObserver invalidates list accounts cache when account is saved', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['name' => 'Original Name']);
    $token = $user->createToken('test');
    $headers = ['Authorization' => 'Bearer '.$token->plainTextToken];

    // Warm the cache via HTTP GET
    $this->getJson('/api/accounts', $headers)->assertSuccessful();

    expect(
        TaggedCache::for(tags: ['accounts', "account:user:{$user->id}"], context: ['userId' => $user->id])->get()
    )->not->toBeNull();

    // Update the account (triggers AccountObserver::saved() which flushes tags)
    $account->update(['name' => 'Updated Name']);

    // Cache should now be invalidated
    expect(
        TaggedCache::for(tags: ['accounts', "account:user:{$user->id}"], context: ['userId' => $user->id])->get()
    )->toBeNull();

    // Second HTTP GET should return updated data (cache miss, re-queries DB)
    $this->getJson('/api/accounts', $headers)
        ->assertSuccessful()
        ->assertJsonFragment(['name' => 'Updated Name']);
});

test('list accounts uses cache on second call', function () {
    $user = User::factory()->create();
    Account::factory()->for($user)->create(['name' => 'Active Account']);
    $token = $user->createToken('test');

    // First request warms cache
    $this->getJson('/api/accounts', [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])->assertSuccessful();

    expect(
        TaggedCache::for(tags: ['accounts', "account:user:{$user->id}"], context: ['userId' => $user->id])->get()
    )->not->toBeNull();

    DB::enableQueryLog();

    // Second request uses cache
    $this->getJson('/api/accounts', [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])->assertSuccessful();

    $queries = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn ($q) => str_contains($q, '"accounts"') && str_contains(strtolower($q), 'select'))
        ->values();

    expect($queries)->toBeEmpty();

    DB::disableQueryLog();
});
