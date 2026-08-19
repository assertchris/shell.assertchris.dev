<?php

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function makeSearchResponse(array $items = []): array
{
    return [
        'total_count' => count($items),
        'incomplete_results' => false,
        'items' => $items,
    ];
}

function makePullItem(array $overrides = []): array
{
    return array_merge([
        'number' => 1,
        'title' => 'Search result PR',
        'state' => 'open',
        'draft' => false,
        'html_url' => 'https://github.com/owner/repo/pull/1',
        'user' => ['login' => 'dev', 'avatar_url' => 'https://avatars.githubusercontent.com/u/1'],
        'created_at' => '2024-01-01T00:00:00Z',
        'updated_at' => '2024-01-01T00:00:00Z',
        'merged_at' => null,
        'head' => ['ref' => 'feature', 'sha' => 'abc123'],
        'base' => ['ref' => 'main', 'sha' => 'def456'],
    ], $overrides);
}

test('search pulls returns results with total_count in meta', function () {
    Http::fake(['https://api.github.com/search/issues*' => Http::response(makeSearchResponse([makePullItem()]), 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withGitHub()->for($user)->create();
    $token = $user->createToken('test');

    $response = $this->getJson("/api/{$account->id}/github/search/pulls?q=fix+bug", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ]);

    $response->assertOk()
        ->assertJsonPath('meta.total_count', 1)
        ->assertJsonStructure(['data' => [['number', 'title', 'state']], 'meta' => ['total_count']]);
});

test('search pulls appends is:pr to query', function () {
    Http::fake(['https://api.github.com/search/issues*' => Http::response(makeSearchResponse(), 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withGitHub()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/github/search/pulls?q=fix+bug", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])->assertOk();

    Http::assertSent(function ($request) {
        parse_str(parse_url($request->url(), PHP_URL_QUERY), $params);

        return str_contains($params['q'] ?? '', 'is:pr');
    });
});

test('search issues appends is:issue to query', function () {
    Http::fake(['https://api.github.com/search/issues*' => Http::response(makeSearchResponse(), 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withGitHub()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/github/search/issues?q=open+bug", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])->assertOk();

    Http::assertSent(function ($request) {
        parse_str(parse_url($request->url(), PHP_URL_QUERY), $params);

        return str_contains($params['q'] ?? '', 'is:issue');
    });
});

test('search pulls returns 422 when q is missing', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withGitHub()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/github/search/pulls", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])->assertUnprocessable();
});

test('search issues returns 422 when q is missing', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withGitHub()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/github/search/issues", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])->assertUnprocessable();
});

test('429 from github search propagates as 429 response', function () {
    Http::fake(['https://api.github.com/search/issues*' => Http::response([], 429, ['Retry-After' => '30'])]);

    $user = User::factory()->create();
    $account = Account::factory()->withGitHub()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/github/search/pulls?q=fix+bug", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])->assertStatus(429);
});
