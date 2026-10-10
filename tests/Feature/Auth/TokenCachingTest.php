<?php

use App\Models\PersonalAccessToken;
use App\Models\User;
use App\Support\CacheKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/**
 * Pre-seed the token's last_ip_address so EnforceTokenIpBinding middleware
 * does not update a security field on first request and bust the cache.
 */
function seedTokenIp(User $user): void
{
    $user->tokens()->update(['last_ip_address' => '127.0.0.1']);
}

test('second identical auth request skips personal_access_tokens DB query', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token');
    $plainText = $token->plainTextToken;
    $rawToken = explode('|', $plainText)[1];
    $hash = hash('sha256', $rawToken);

    seedTokenIp($user);

    // Warm the cache with first request
    $this->getJson('/api/status', ['Authorization' => 'Bearer '.$plainText])->assertSuccessful();

    // Verify cache is warm
    expect(Cache::has(CacheKey::for('token', $hash)))->toBeTrue();

    DB::enableQueryLog();

    // Second request should not query personal_access_tokens
    $this->getJson('/api/status', ['Authorization' => 'Bearer '.$plainText])->assertSuccessful();

    $queries = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn ($q) => str_contains($q, 'personal_access_tokens'))
        ->values();

    expect($queries)->toBeEmpty();

    DB::disableQueryLog();
});

test('last_used_at update does not write to DB', function () {
    $user = User::factory()->create();
    $tokenResult = $user->createToken('test-token');
    $rawToken = explode('|', $tokenResult->plainTextToken)[1];
    $hash = hash('sha256', $rawToken);

    seedTokenIp($user);

    // Warm the token cache
    $this->getJson('/api/status', ['Authorization' => 'Bearer '.$tokenResult->plainTextToken])->assertSuccessful();

    $pat = PersonalAccessToken::where('token', $hash)->first();

    DB::enableQueryLog();

    // Simulate Sanctum's last_used_at update
    $pat->forceFill(['last_used_at' => now()])->save();

    $queries = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn ($q) => str_contains(strtolower($q), 'update') && str_contains($q, 'personal_access_tokens'))
        ->values();

    expect($queries)->toBeEmpty();

    DB::disableQueryLog();
});

test('security field change writes to DB and busts cache', function () {
    $user = User::factory()->create();
    $tokenResult = $user->createToken('test-token');
    $rawToken = explode('|', $tokenResult->plainTextToken)[1];
    $hash = hash('sha256', $rawToken);

    seedTokenIp($user);

    // Warm the token cache
    $this->getJson('/api/status', ['Authorization' => 'Bearer '.$tokenResult->plainTextToken])->assertSuccessful();

    expect(Cache::has(CacheKey::for('token', $hash)))->toBeTrue();

    $pat = PersonalAccessToken::where('token', $hash)->first();

    DB::enableQueryLog();

    // Change a security-relevant field
    $pat->forceFill(['last_ip_address' => '1.2.3.4'])->save();

    $queries = collect(DB::getQueryLog())
        ->pluck('query')
        ->filter(fn ($q) => str_contains(strtolower($q), 'update') && str_contains($q, 'personal_access_tokens'))
        ->values();

    expect($queries)->not->toBeEmpty();
    expect(Cache::has(CacheKey::for('token', $hash)))->toBeFalse();

    DB::disableQueryLog();
});

test('token deletion busts cache', function () {
    $user = User::factory()->create();
    $tokenResult = $user->createToken('test-token');
    $rawToken = explode('|', $tokenResult->plainTextToken)[1];
    $hash = hash('sha256', $rawToken);

    seedTokenIp($user);

    // Warm the token cache
    $this->getJson('/api/status', ['Authorization' => 'Bearer '.$tokenResult->plainTextToken])->assertSuccessful();

    expect(Cache::has(CacheKey::for('token', $hash)))->toBeTrue();

    $pat = PersonalAccessToken::where('token', $hash)->first();
    $pat->delete();

    expect(Cache::has(CacheKey::for('token', $hash)))->toBeFalse();
});
