<?php

use App\Models\Account;
use App\Models\User;
use App\Support\CacheKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('route model binding serves account from cache after first request', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->withSlack()->create();
    $token = $user->createToken('test');

    // First request warms cache
    $this->getJson("/api/slack/{$account->id}/channels", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ]);

    expect(Cache::has((string) CacheKey::for('account', $account->id)))->toBeTrue();

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

test('list accounts uses cache on second call', function () {
    $user = User::factory()->create();
    Account::factory()->for($user)->create(['name' => 'Active Account']);
    $token = $user->createToken('test');

    // First request warms cache
    $this->getJson('/api/accounts', [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])->assertSuccessful();

    expect(Cache::has((string) CacheKey::for('accounts', 'user', $user->id)))->toBeTrue();

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

test('AccountObserver busts account and accounts:user keys on save', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();

    // Warm both cache entries
    Cache::put((string) CacheKey::for('account', $account->id), $account, now()->addDays(7));
    Cache::put((string) CacheKey::for('accounts', 'user', $user->id), collect([$account]), now()->addDays(7));

    expect(Cache::has((string) CacheKey::for('account', $account->id)))->toBeTrue();
    expect(Cache::has((string) CacheKey::for('accounts', 'user', $user->id)))->toBeTrue();

    $account->name = 'Updated';
    $account->save();

    expect(Cache::has((string) CacheKey::for('account', $account->id)))->toBeFalse();
    expect(Cache::has((string) CacheKey::for('accounts', 'user', $user->id)))->toBeFalse();
});
