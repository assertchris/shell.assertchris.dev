<?php

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function makeDashboardData(array $overrides = []): array
{
    return array_merge([
        'id' => '10001',
        'name' => 'My Dashboard',
        'description' => 'A test dashboard',
        'popularity' => 5,
    ], $overrides);
}

test('list dashboards returns 200 with data', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response([
        'dashboards' => [makeDashboardData()],
        'total' => 1,
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

test('list dashboards includes total_count in meta', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response([
        'dashboards' => [makeDashboardData(), makeDashboardData(['id' => '10002', 'name' => 'Second'])],
        'total' => 2,
    ], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/jira/dashboards", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonPath('meta.total_count', 2);
});
