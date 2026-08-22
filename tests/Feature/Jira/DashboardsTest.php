<?php

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('list dashboards returns dashboard collection with data', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response([
        'dashboards' => [
            [
                'id' => '10001',
                'name' => 'My Dashboard',
                'description' => 'Team overview',
                'popularity' => 5,
            ],
        ],
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/jira/dashboards", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'name']], 'meta' => ['total_count']]);
});

test('list dashboards returns empty collection when none exist', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response([
        'dashboards' => [],
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $response = $this->getJson("/api/{$account->id}/jira/dashboards", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ]);

    $response->assertOk()
        ->assertJsonPath('data', [])
        ->assertJsonPath('meta.total_count', 0);
});
