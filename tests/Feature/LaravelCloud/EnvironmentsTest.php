<?php

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function makeEnvironmentItem(array $overrides = []): array
{
    return array_merge([
        'id' => 'env-uuid-1234',
        'type' => 'environments',
        'attributes' => [
            'name' => 'production',
            'slug' => 'my-app',
            'status' => 'running',
            'color' => 'green',
            'vanity_domain' => 'my-app.laravel.cloud',
            'framework' => 'laravel',
            'language' => 'php',
            'php_major_version' => '8.3',
            'serving' => true,
            'uses_octane' => false,
            'uses_hibernation' => false,
            'timeout' => 30,
            'shutdown_timeout' => 30,
            'uses_push_to_deploy' => true,
            'uses_deploy_hook' => false,
            'network_settings' => null,
            'created_at' => '2024-01-01T00:00:00Z',
            'environment_variables' => [
                'APP_KEY' => 'secret',
            ],
        ],
    ], $overrides);
}

test('list environments returns 200 with data array', function () {
    Http::fake(['https://cloud.laravel.com/*' => Http::response([
        'data' => [makeEnvironmentItem()],
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withLaravelCloud()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/laravel-cloud/applications/app-uuid-1234/environments", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonStructure(['data']);
});

test('list environments returns correct shape', function () {
    Http::fake(['https://cloud.laravel.com/*' => Http::response([
        'data' => [makeEnvironmentItem()],
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withLaravelCloud()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/laravel-cloud/applications/app-uuid-1234/environments", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonPath('data.0.id', 'env-uuid-1234')
        ->assertJsonPath('data.0.name', 'production')
        ->assertJsonPath('data.0.status', 'running');
});

test('get single environment returns 200 with correct data', function () {
    Http::fake(['https://cloud.laravel.com/*' => Http::response([
        'data' => makeEnvironmentItem(),
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withLaravelCloud()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/laravel-cloud/environments/env-uuid-1234", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonPath('data.id', 'env-uuid-1234')
        ->assertJsonPath('data.name', 'production')
        ->assertJsonPath('data.status', 'running')
        ->assertJsonPath('data.serving', true);
});

test('get single environment does not expose environment_variables', function () {
    Http::fake(['https://cloud.laravel.com/*' => Http::response([
        'data' => makeEnvironmentItem(),
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withLaravelCloud()->for($user)->create();
    $token = $user->createToken('test');

    $response = $this->getJson("/api/{$account->id}/laravel-cloud/environments/env-uuid-1234", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])->assertOk();

    expect($response->json('data'))->not->toHaveKey('environment_variables');
});

test('environment logs returns 200', function () {
    Http::fake(['https://cloud.laravel.com/*' => Http::response([
        'data' => [
            ['message' => 'App started', 'level' => 'info', 'type' => 'app', 'logged_at' => '2024-01-01T00:00:00Z', 'data' => null],
        ],
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withLaravelCloud()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/laravel-cloud/environments/env-uuid-1234/logs?from=2024-01-01T00:00:00Z&to=2024-01-02T00:00:00Z", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonPath('data.0.message', 'App started')
        ->assertJsonPath('data.0.level', 'info');
});

test('environment metrics returns 200 with plain object shape', function () {
    Http::fake(['https://cloud.laravel.com/*' => Http::response([
        'data' => [
            'cpu_usage' => ['labels' => [], 'data' => [], 'average' => 0.5],
            'memory_usage' => ['labels' => [], 'data' => [], 'average' => 256],
        ],
        'meta' => [
            'period' => '24h',
            'available_periods' => ['6h', '24h', '3d', '7d', '30d'],
        ],
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withLaravelCloud()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/laravel-cloud/environments/env-uuid-1234/metrics", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonStructure(['data', 'meta']);
});

test('start environment returns 200', function () {
    Http::fake(['https://cloud.laravel.com/*' => Http::response([], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withLaravelCloud()->for($user)->create();
    $token = $user->createToken('test');

    $this->postJson("/api/{$account->id}/laravel-cloud/environments/env-uuid-1234/start", [], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk();
});

test('stop environment returns 200', function () {
    Http::fake(['https://cloud.laravel.com/*' => Http::response([], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withLaravelCloud()->for($user)->create();
    $token = $user->createToken('test');

    $this->postJson("/api/{$account->id}/laravel-cloud/environments/env-uuid-1234/stop", [], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk();
});
