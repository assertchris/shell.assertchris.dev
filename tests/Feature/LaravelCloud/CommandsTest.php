<?php

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function makeCommandItem(array $overrides = []): array
{
    return array_merge([
        'id' => 'cmd-uuid-1234',
        'type' => 'commands',
        'attributes' => [
            'command' => 'php artisan cache:clear',
            'output' => null,
            'status' => 'pending',
            'exit_code' => null,
            'failure_reason' => null,
            'started_at' => null,
            'finished_at' => null,
            'created_at' => '2024-01-01T00:00:00Z',
        ],
    ], $overrides);
}

test('list commands returns 200 with data array', function () {
    Http::fake(['https://cloud.laravel.com/*' => Http::response([
        'data' => [makeCommandItem()],
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withLaravelCloud()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/laravel-cloud/environments/env-uuid-1234/commands", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonStructure(['data'])
        ->assertJsonPath('data.0.id', 'cmd-uuid-1234')
        ->assertJsonPath('data.0.command', 'php artisan cache:clear');
});

test('get command returns 200 with pending status and null exit_code', function () {
    Http::fake(['https://cloud.laravel.com/*' => Http::response([
        'data' => makeCommandItem(),
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withLaravelCloud()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/laravel-cloud/commands/cmd-uuid-1234", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonPath('data.id', 'cmd-uuid-1234')
        ->assertJsonPath('data.command', 'php artisan cache:clear')
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.exit_code', null);
});

test('run command returns 200 with pending command resource', function () {
    Http::fake(['https://cloud.laravel.com/*' => Http::response([
        'data' => makeCommandItem(),
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withLaravelCloud()->for($user)->create();
    $token = $user->createToken('test');

    $this->postJson("/api/{$account->id}/laravel-cloud/environments/env-uuid-1234/commands", [
        'command' => 'php artisan cache:clear',
    ], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonPath('data.command', 'php artisan cache:clear')
        ->assertJsonPath('data.status', 'pending');
});

test('run command validates command field is required', function () {
    Http::fake(['https://cloud.laravel.com/*' => Http::response([], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withLaravelCloud()->for($user)->create();
    $token = $user->createToken('test');

    $this->postJson("/api/{$account->id}/laravel-cloud/environments/env-uuid-1234/commands", [], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertUnprocessable();
});

test('get command with exit_code 1 reflects failure shape', function () {
    Http::fake(['https://cloud.laravel.com/*' => Http::response([
        'data' => makeCommandItem([
            'attributes' => [
                'command' => 'php artisan migrate',
                'output' => 'SQLSTATE error',
                'status' => 'command.failure',
                'exit_code' => 1,
                'failure_reason' => 'Non-zero exit code',
                'started_at' => '2024-01-01T00:00:00Z',
                'finished_at' => '2024-01-01T00:00:05Z',
                'created_at' => '2024-01-01T00:00:00Z',
            ],
        ]),
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withLaravelCloud()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/laravel-cloud/commands/cmd-uuid-1234", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'command.failure')
        ->assertJsonPath('data.exit_code', 1)
        ->assertJsonPath('data.failure_reason', 'Non-zero exit code');
});
