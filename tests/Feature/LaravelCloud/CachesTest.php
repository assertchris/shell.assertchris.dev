<?php

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function makeCacheItem(array $overrides = []): array
{
    return array_merge([
        'id' => 'cache-uuid-1234',
        'type' => 'caches',
        'attributes' => [
            'name' => 'production-cache',
            'type' => 'redis',
            'status' => 'available',
            'region' => 'us-east-1',
            'size' => 'cache-1',
            'auto_upgrade_enabled' => true,
            'is_public' => false,
            'eviction_policy' => 'allkeys-lru',
            'uses_hibernation' => false,
            'hibernation_timeout' => null,
        ],
    ], $overrides);
}

test('list caches returns 200 with data array', function () {
    Http::fake(['https://cloud.laravel.com/*' => Http::response([
        'data' => [makeCacheItem()],
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withLaravelCloud()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/laravel-cloud/caches", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonStructure(['data'])
        ->assertJsonPath('data.0.id', 'cache-uuid-1234')
        ->assertJsonPath('data.0.name', 'production-cache')
        ->assertJsonPath('data.0.status', 'available');
});

test('get cache returns 200 with correct data', function () {
    Http::fake(['https://cloud.laravel.com/*' => Http::response([
        'data' => makeCacheItem(),
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withLaravelCloud()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/laravel-cloud/caches/cache-uuid-1234", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonPath('data.id', 'cache-uuid-1234')
        ->assertJsonPath('data.name', 'production-cache')
        ->assertJsonPath('data.type', 'redis')
        ->assertJsonPath('data.status', 'available')
        ->assertJsonPath('data.eviction_policy', 'allkeys-lru');
});

test('cache metrics returns 200', function () {
    Http::fake(['https://cloud.laravel.com/*' => Http::response([
        'data' => [
            'memory_usage' => ['labels' => [], 'data' => [], 'average' => 64],
        ],
        'meta' => [
            'period' => '24h',
            'available_periods' => ['6h', '24h', '3d', '7d', '30d'],
        ],
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withLaravelCloud()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/laravel-cloud/caches/cache-uuid-1234/metrics", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonStructure(['data', 'meta']);
});

test('purge edge cache with path returns 200', function () {
    Http::fake(['https://cloud.laravel.com/*' => Http::response([], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withLaravelCloud()->for($user)->create();
    $token = $user->createToken('test');

    $this->postJson("/api/{$account->id}/laravel-cloud/environments/env-uuid-1234/purge-cache", [
        'path' => '/foo',
    ], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk();
});

test('purge edge cache with prefix returns 200', function () {
    Http::fake(['https://cloud.laravel.com/*' => Http::response([], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withLaravelCloud()->for($user)->create();
    $token = $user->createToken('test');

    $this->postJson("/api/{$account->id}/laravel-cloud/environments/env-uuid-1234/purge-cache", [
        'prefix' => '/api',
    ], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk();
});

test('purge edge cache with tag returns 200', function () {
    Http::fake(['https://cloud.laravel.com/*' => Http::response([], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withLaravelCloud()->for($user)->create();
    $token = $user->createToken('test');

    $this->postJson("/api/{$account->id}/laravel-cloud/environments/env-uuid-1234/purge-cache", [
        'tag' => 'products',
    ], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk();
});

test('purge edge cache returns 422 when none of path prefix or tag is provided', function () {
    Http::fake(['https://cloud.laravel.com/*' => Http::response([], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withLaravelCloud()->for($user)->create();
    $token = $user->createToken('test');

    $this->postJson("/api/{$account->id}/laravel-cloud/environments/env-uuid-1234/purge-cache", [], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertStatus(422);
});

test('429 from laravel cloud propagates as 429 with retry-after header', function () {
    Http::fake(['https://cloud.laravel.com/*' => Http::response([], 429, ['Retry-After' => '30'])]);

    $user = User::factory()->create();
    $account = Account::factory()->withLaravelCloud()->for($user)->create();
    $token = $user->createToken('test');

    $response = $this->getJson("/api/{$account->id}/laravel-cloud/caches", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ]);

    $response->assertStatus(429);
    expect($response->headers->get('Retry-After'))->toBe('30');
});
