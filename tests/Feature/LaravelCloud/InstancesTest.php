<?php

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function makeInstanceItem(array $overrides = []): array
{
    return array_merge([
        'id' => 'inst-uuid-1234',
        'type' => 'instances',
        'attributes' => [
            'status' => 'running',
            'region' => 'us-east-1',
            'created_at' => '2024-01-01T00:00:00Z',
        ],
    ], $overrides);
}

function makeFailedJobItem(array $overrides = []): array
{
    return array_merge([
        'id' => 'job-uuid-1234',
        'type' => 'failed_jobs',
        'attributes' => [
            'name' => 'App\Jobs\SendEmail',
            'queue' => 'default',
            'failed_at' => '2024-01-01T01:00:00Z',
            'started_at' => '2024-01-01T00:59:55Z',
            'attempts' => 3,
            'exception' => 'Connection refused',
            'retried_at' => null,
            'retry_reserved_until' => null,
        ],
    ], $overrides);
}

test('list instances returns 200 with data array', function () {
    Http::fake(['https://cloud.laravel.com/*' => Http::response([
        'data' => [makeInstanceItem()],
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withLaravelCloud()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/laravel-cloud/environments/env-uuid-1234/instances", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonStructure(['data'])
        ->assertJsonPath('data.0.id', 'inst-uuid-1234')
        ->assertJsonPath('data.0.status', 'running');
});

test('get instance returns 200 with correct data', function () {
    Http::fake(['https://cloud.laravel.com/*' => Http::response([
        'data' => makeInstanceItem(),
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withLaravelCloud()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/laravel-cloud/instances/inst-uuid-1234", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonPath('data.id', 'inst-uuid-1234')
        ->assertJsonPath('data.status', 'running')
        ->assertJsonPath('data.region', 'us-east-1');
});

test('list failed jobs returns 200 with data array', function () {
    Http::fake(['https://cloud.laravel.com/*' => Http::response([
        'data' => [makeFailedJobItem()],
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withLaravelCloud()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/laravel-cloud/instances/inst-uuid-1234/failed-jobs", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonStructure(['data'])
        ->assertJsonPath('data.0.id', 'job-uuid-1234')
        ->assertJsonPath('data.0.name', 'App\Jobs\SendEmail');
});

test('retry failed job returns 200', function () {
    Http::fake(['https://cloud.laravel.com/*' => Http::response([], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withLaravelCloud()->for($user)->create();
    $token = $user->createToken('test');

    $this->postJson("/api/{$account->id}/laravel-cloud/instances/inst-uuid-1234/failed-jobs/job-uuid-1234/retry", [], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk();
});

test('pause queue returns 200', function () {
    Http::fake(['https://cloud.laravel.com/*' => Http::response([], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withLaravelCloud()->for($user)->create();
    $token = $user->createToken('test');

    $this->postJson("/api/{$account->id}/laravel-cloud/instances/inst-uuid-1234/pause", [], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk();
});

test('resume queue returns 200', function () {
    Http::fake(['https://cloud.laravel.com/*' => Http::response([], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withLaravelCloud()->for($user)->create();
    $token = $user->createToken('test');

    $this->postJson("/api/{$account->id}/laravel-cloud/instances/inst-uuid-1234/resume", [], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk();
});

test('purge queue returns 200', function () {
    Http::fake(['https://cloud.laravel.com/*' => Http::response([], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withLaravelCloud()->for($user)->create();
    $token = $user->createToken('test');

    $this->postJson("/api/{$account->id}/laravel-cloud/instances/inst-uuid-1234/purge", [], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk();
});
