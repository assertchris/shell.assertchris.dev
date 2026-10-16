<?php

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function makeDeploymentItem(array $overrides = []): array
{
    return array_merge([
        'id' => 'depl-uuid-1234',
        'type' => 'deployments',
        'attributes' => [
            'slug' => '1',
            'status' => 'succeeded',
            'branch_name' => 'main',
            'commit_hash' => 'abc1234',
            'commit_message' => 'Initial commit',
            'commit_author' => 'Alice',
            'failure_reason' => null,
            'php_major_version' => '8.3',
            'build_command' => 'npm run build',
            'node_version' => '20',
            'uses_octane' => false,
            'uses_hibernation' => false,
            'started_at' => '2024-01-01T00:00:00Z',
            'finished_at' => '2024-01-01T00:05:00Z',
        ],
    ], $overrides);
}

test('list deployments returns 200 with data array', function () {
    Http::fake(['https://cloud.laravel.com/*' => Http::response([
        'data' => [makeDeploymentItem()],
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withLaravelCloud()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/laravel-cloud/environments/env-uuid-1234/deployments", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonStructure(['data'])
        ->assertJsonPath('data.0.id', 'depl-uuid-1234')
        ->assertJsonPath('data.0.slug', '1')
        ->assertJsonPath('data.0.status', 'succeeded');
});

test('get single deployment returns 200 with correct data', function () {
    Http::fake(['https://cloud.laravel.com/*' => Http::response([
        'data' => makeDeploymentItem(),
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withLaravelCloud()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/laravel-cloud/deployments/depl-uuid-1234", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonPath('data.id', 'depl-uuid-1234')
        ->assertJsonPath('data.slug', '1')
        ->assertJsonPath('data.status', 'succeeded')
        ->assertJsonPath('data.branch_name', 'main')
        ->assertJsonPath('data.commit_hash', 'abc1234');
});

test('deployment logs returns 200', function () {
    Http::fake(['https://cloud.laravel.com/*' => Http::response([
        'data' => [
            ['message' => 'Build started', 'level' => 'info', 'type' => 'build', 'logged_at' => '2024-01-01T00:00:00Z', 'data' => null],
        ],
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withLaravelCloud()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/laravel-cloud/deployments/depl-uuid-1234/logs", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonPath('data.0.message', 'Build started')
        ->assertJsonPath('data.0.level', 'info');
});
