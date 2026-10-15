<?php

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function makeApplicationItem(array $overrides = []): array
{
    return array_merge([
        'id' => 'app-uuid-1234',
        'type' => 'applications',
        'attributes' => [
            'name' => 'My App',
            'slug' => 'my-app',
            'region' => 'us-east-1',
            'root_directory' => '/',
            'slack_channel' => null,
            'avatar_url' => null,
            'framework' => 'laravel',
            'language' => 'php',
            'created_at' => '2024-01-01T00:00:00Z',
            'repository' => [
                'full_name' => 'org/my-app',
                'default_branch' => 'main',
            ],
        ],
    ], $overrides);
}

test('list applications returns 200 with data array', function () {
    Http::fake(['https://cloud.laravel.com/*' => Http::response([
        'data' => [makeApplicationItem()],
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withLaravelCloud()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/laravel-cloud/applications", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonStructure(['data']);
});

test('list applications returns correct shape for each item', function () {
    Http::fake(['https://cloud.laravel.com/*' => Http::response([
        'data' => [makeApplicationItem()],
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withLaravelCloud()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/laravel-cloud/applications", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonPath('data.0.id', 'app-uuid-1234')
        ->assertJsonPath('data.0.name', 'My App')
        ->assertJsonPath('data.0.slug', 'my-app')
        ->assertJsonPath('data.0.repository.full_name', 'org/my-app');
});

test('get single application returns 200 with correct data', function () {
    Http::fake(['https://cloud.laravel.com/*' => Http::response([
        'data' => makeApplicationItem(),
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withLaravelCloud()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/laravel-cloud/applications/app-uuid-1234", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonPath('data.id', 'app-uuid-1234')
        ->assertJsonPath('data.name', 'My App')
        ->assertJsonPath('data.slug', 'my-app')
        ->assertJsonPath('data.region', 'us-east-1')
        ->assertJsonPath('data.framework', 'laravel')
        ->assertJsonPath('data.repository.full_name', 'org/my-app')
        ->assertJsonPath('data.repository.default_branch', 'main');
});

test('get single application returns 404 when service returns empty', function () {
    Http::fake(['https://cloud.laravel.com/*' => Http::response([], 404)]);

    $user = User::factory()->create();
    $account = Account::factory()->withLaravelCloud()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/laravel-cloud/applications/app-missing", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});
