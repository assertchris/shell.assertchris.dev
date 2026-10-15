<?php

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('get organization returns 200 with id, name, and slug', function () {
    Http::fake(['https://cloud.laravel.com/*' => Http::response([
        'data' => [
            'id' => 'org-uuid-1234',
            'type' => 'organizations',
            'attributes' => [
                'name' => 'Acme Corp',
                'slug' => 'acme-corp',
            ],
        ],
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withLaravelCloud()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/laravel-cloud/organization", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonPath('data.id', 'org-uuid-1234')
        ->assertJsonPath('data.name', 'Acme Corp')
        ->assertJsonPath('data.slug', 'acme-corp');
});

test('list regions returns 200 with region, label, and flag items', function () {
    Http::fake(['https://cloud.laravel.com/*' => Http::response([
        'data' => [
            ['region' => 'us-east-1', 'label' => 'US East (N. Virginia)', 'flag' => '🇺🇸'],
            ['region' => 'eu-west-1', 'label' => 'EU West (Ireland)', 'flag' => '🇮🇪'],
        ],
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withLaravelCloud()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/laravel-cloud/regions", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonStructure(['data'])
        ->assertJsonPath('data.0.region', 'us-east-1')
        ->assertJsonPath('data.0.label', 'US East (N. Virginia)')
        ->assertJsonPath('data.1.region', 'eu-west-1');
});

test('get usage returns 200 and passes data through', function () {
    Http::fake(['https://cloud.laravel.com/*' => Http::response([
        'data' => [
            'summary' => ['total' => 100.00],
            'resources' => [],
            'addons' => [],
            'application_totals' => [],
            'environment_usage' => [],
            'private_cloud' => null,
        ],
        'meta' => [
            'currency' => 'USD',
            'period' => '2026-10',
            'available_periods' => ['2026-09', '2026-10'],
            'last_updated_at' => '2026-10-15T00:00:00Z',
        ],
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withLaravelCloud()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/laravel-cloud/usage", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonStructure(['data', 'meta']);
});
