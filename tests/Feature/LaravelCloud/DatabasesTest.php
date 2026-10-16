<?php

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function makeDatabaseItem(array $overrides = []): array
{
    return array_merge([
        'id' => 'db-uuid-1234',
        'type' => 'databases',
        'attributes' => [
            'name' => 'production-db',
            'type' => 'mysql',
            'status' => 'available',
            'region' => 'us-east-1',
            'size' => 'db-1',
            'storage' => 20,
            'is_public' => false,
            'created_at' => '2024-01-01T00:00:00Z',
        ],
    ], $overrides);
}

test('list databases returns 200 with data array', function () {
    Http::fake(['https://cloud.laravel.com/*' => Http::response([
        'data' => [makeDatabaseItem()],
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withLaravelCloud()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/laravel-cloud/databases", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonStructure(['data'])
        ->assertJsonPath('data.0.id', 'db-uuid-1234')
        ->assertJsonPath('data.0.name', 'production-db')
        ->assertJsonPath('data.0.status', 'available');
});

test('get database returns 200 with correct data', function () {
    Http::fake(['https://cloud.laravel.com/*' => Http::response([
        'data' => makeDatabaseItem(),
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withLaravelCloud()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/laravel-cloud/databases/db-uuid-1234", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonPath('data.id', 'db-uuid-1234')
        ->assertJsonPath('data.name', 'production-db')
        ->assertJsonPath('data.type', 'mysql')
        ->assertJsonPath('data.status', 'available')
        ->assertJsonPath('data.region', 'us-east-1');
});

test('database metrics returns 200', function () {
    Http::fake(['https://cloud.laravel.com/*' => Http::response([
        'data' => [
            'cpu_usage' => ['labels' => [], 'data' => [], 'average' => 0.2],
            'memory_usage' => ['labels' => [], 'data' => [], 'average' => 128],
        ],
        'meta' => [
            'period' => '24h',
            'available_periods' => ['6h', '24h', '3d', '7d', '30d'],
        ],
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withLaravelCloud()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/laravel-cloud/databases/db-uuid-1234/metrics", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonStructure(['data', 'meta']);
});
