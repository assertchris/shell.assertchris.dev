<?php

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function makeIssueData(array $overrides = []): array
{
    return array_merge([
        'number' => 1,
        'title' => 'Bug report',
        'state' => 'open',
        'html_url' => 'https://github.com/owner/repo/issues/1',
        'body' => 'Something is broken.',
        'user' => ['login' => 'reporter'],
        'labels' => [['name' => 'bug', 'color' => 'ee0701']],
        'created_at' => '2024-01-01T00:00:00Z',
        'updated_at' => '2024-01-02T00:00:00Z',
        'closed_at' => null,
    ], $overrides);
}

function makeCommentData(array $overrides = []): array
{
    return array_merge([
        'id' => 10,
        'user' => ['login' => 'commenter'],
        'body' => 'This is a comment.',
        'created_at' => '2024-01-03T00:00:00Z',
        'updated_at' => '2024-01-03T00:00:00Z',
    ], $overrides);
}

test('list issues returns issue collection', function () {
    Http::fake(['https://api.github.com/issues*' => Http::response([makeIssueData()], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withGitHub()->for($user)->create();
    $token = $user->createToken('test');

    $response = $this->getJson("/api/{$account->id}/github/issues", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ]);

    $response->assertOk()
        ->assertJsonStructure(['data' => [['number', 'title', 'state', 'html_url', 'labels']], 'meta' => ['total_count']]);
});

test('list user issues returns issue collection', function () {
    Http::fake(['https://api.github.com/user/issues*' => Http::response([makeIssueData()], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withGitHub()->for($user)->create();
    $token = $user->createToken('test');

    $response = $this->getJson("/api/{$account->id}/github/user/issues", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ]);

    $response->assertOk()
        ->assertJsonStructure(['data' => [['number', 'title', 'state']], 'meta' => ['total_count']]);
});

test('list repo issues returns issue collection', function () {
    Http::fake(['https://api.github.com/repos/owner/repo/issues*' => Http::response([makeIssueData()], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withGitHub()->for($user)->create();
    $token = $user->createToken('test');

    $response = $this->getJson("/api/{$account->id}/github/repos/owner/repo/issues", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ]);

    $response->assertOk()
        ->assertJsonStructure(['data' => [['number', 'title', 'state']], 'meta' => ['total_count']]);
});

test('get single issue returns issue data', function () {
    Http::fake(['https://api.github.com/repos/owner/repo/issues/1*' => Http::response(makeIssueData(), 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withGitHub()->for($user)->create();
    $token = $user->createToken('test');

    $response = $this->getJson("/api/{$account->id}/github/repos/owner/repo/issues/1", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.number', 1)
        ->assertJsonPath('data.title', 'Bug report');
});

test('get issue returns 404 when not found', function () {
    Http::fake(['https://api.github.com/repos/owner/repo/issues/999*' => Http::response([], 404)]);

    $user = User::factory()->create();
    $account = Account::factory()->withGitHub()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/github/repos/owner/repo/issues/999", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])->assertNotFound();
});

test('list issue comments returns comment collection', function () {
    Http::fake(['https://api.github.com/repos/owner/repo/issues/1/comments*' => Http::response([makeCommentData()], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withGitHub()->for($user)->create();
    $token = $user->createToken('test');

    $response = $this->getJson("/api/{$account->id}/github/repos/owner/repo/issues/1/comments", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ]);

    $response->assertOk()
        ->assertJsonStructure(['data' => [['id', 'user', 'body']], 'meta' => ['total_count']]);
});

test('list issues forwards state filter', function () {
    Http::fake(['https://api.github.com/issues*' => Http::response([], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withGitHub()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/github/issues?state=closed", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])->assertOk();

    Http::assertSent(function ($request) {
        parse_str(parse_url($request->url(), PHP_URL_QUERY), $params);

        return $params['state'] === 'closed';
    });
});

test('list repo issues forwards labels filter', function () {
    Http::fake(['https://api.github.com/repos/owner/repo/issues*' => Http::response([], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withGitHub()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/github/repos/owner/repo/issues?labels=bug,enhancement", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])->assertOk();

    Http::assertSent(function ($request) {
        parse_str(parse_url($request->url(), PHP_URL_QUERY), $params);

        return $params['labels'] === 'bug,enhancement';
    });
});

test('429 from github issues propagates as 429 response', function () {
    Http::fake(['https://api.github.com/issues*' => Http::response([], 429, ['Retry-After' => '60'])]);

    $user = User::factory()->create();
    $account = Account::factory()->withGitHub()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/github/issues", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])->assertStatus(429);
});
