<?php

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function makePullData(array $overrides = []): array
{
    return array_merge([
        'number' => 1,
        'title' => 'Fix bug',
        'state' => 'open',
        'draft' => false,
        'html_url' => 'https://github.com/owner/repo/pull/1',
        'user' => ['login' => 'dev', 'avatar_url' => 'https://avatars.githubusercontent.com/u/1'],
        'created_at' => '2024-01-01T00:00:00Z',
        'updated_at' => '2024-01-02T00:00:00Z',
        'merged_at' => null,
        'head' => ['ref' => 'feature-branch', 'sha' => 'abc123'],
        'base' => ['ref' => 'main', 'sha' => 'def456'],
    ], $overrides);
}

test('list pulls returns open PRs by default', function () {
    Http::fake(['https://api.github.com/repos/owner/repo/pulls*' => Http::response([makePullData()], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withGitHub()->for($user)->create();
    $token = $user->createToken('test');

    $response = $this->getJson("/api/{$account->id}/github/repos/owner/repo/pulls", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ]);

    $response->assertOk()
        ->assertJsonStructure(['data' => [['number', 'title', 'state', 'html_url']], 'meta' => ['total_count']]);
});

test('list pulls forwards state filter to github api', function () {
    Http::fake(['https://api.github.com/repos/owner/repo/pulls*' => Http::response([], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withGitHub()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/github/repos/owner/repo/pulls?state=closed", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])->assertOk();

    Http::assertSent(function ($request) {
        parse_str(parse_url($request->url(), PHP_URL_QUERY), $params);

        return $params['state'] === 'closed';
    });
});

test('open pull list is cached', function () {
    Cache::flush();
    Http::fake(['https://api.github.com/repos/owner/repo/pulls*' => Http::response([makePullData()], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withGitHub()->for($user)->create();
    $token = $user->createToken('test');

    $headers = ['Authorization' => 'Bearer '.$token->plainTextToken];
    $url = "/api/{$account->id}/github/repos/owner/repo/pulls?state=open";

    $this->getJson($url, $headers)->assertOk();
    $this->getJson($url, $headers)->assertOk();

    Http::assertSentCount(1);
});

test('closed pull list is cached', function () {
    Cache::flush();
    Http::fake(['https://api.github.com/repos/owner/repo/pulls*' => Http::response([makePullData(['state' => 'closed'])], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withGitHub()->for($user)->create();
    $token = $user->createToken('test');

    $headers = ['Authorization' => 'Bearer '.$token->plainTextToken];
    $url = "/api/{$account->id}/github/repos/owner/repo/pulls?state=closed";

    $this->getJson($url, $headers)->assertOk();
    $this->getJson($url, $headers)->assertOk();

    Http::assertSentCount(1);
});

test('get single PR returns pull data', function () {
    Http::fake(['https://api.github.com/repos/owner/repo/pulls/1*' => Http::response(makePullData(), 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withGitHub()->for($user)->create();
    $token = $user->createToken('test');

    $response = $this->getJson("/api/{$account->id}/github/repos/owner/repo/pulls/1", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.number', 1)
        ->assertJsonPath('data.title', 'Fix bug');
});

test('get PR returns 404 when not found', function () {
    Http::fake(['https://api.github.com/repos/owner/repo/pulls/999*' => Http::response([], 404)]);

    $user = User::factory()->create();
    $account = Account::factory()->withGitHub()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/github/repos/owner/repo/pulls/999", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])->assertNotFound();
});

test('get pull files returns file list', function () {
    $fileData = [
        'filename' => 'src/app.php',
        'status' => 'modified',
        'additions' => 5,
        'deletions' => 2,
        'changes' => 7,
        'patch' => '@@ -1,4 +1,7 @@',
    ];
    Http::fake(['https://api.github.com/repos/owner/repo/pulls/1/files*' => Http::response([$fileData], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withGitHub()->for($user)->create();
    $token = $user->createToken('test');

    $response = $this->getJson("/api/{$account->id}/github/repos/owner/repo/pulls/1/files", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ]);

    $response->assertOk()
        ->assertJsonStructure(['data' => [['filename', 'status', 'additions', 'deletions', 'changes']]]);
});

test('get pull commits returns commit list', function () {
    $commitData = [
        'sha' => 'abc123',
        'commit' => ['message' => 'Fix bug', 'author' => ['name' => 'Dev', 'date' => '2024-01-01T00:00:00Z']],
        'html_url' => 'https://github.com/owner/repo/commit/abc123',
    ];
    Http::fake(['https://api.github.com/repos/owner/repo/pulls/1/commits*' => Http::response([$commitData], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withGitHub()->for($user)->create();
    $token = $user->createToken('test');

    $response = $this->getJson("/api/{$account->id}/github/repos/owner/repo/pulls/1/commits", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ]);

    $response->assertOk()
        ->assertJsonStructure(['data' => [['sha', 'commit', 'html_url']]]);
});

test('get pull reviews returns review list', function () {
    $reviewData = [
        'id' => 1,
        'user' => ['login' => 'reviewer'],
        'state' => 'APPROVED',
        'body' => 'LGTM',
        'submitted_at' => '2024-01-02T00:00:00Z',
    ];
    Http::fake(['https://api.github.com/repos/owner/repo/pulls/1/reviews*' => Http::response([$reviewData], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withGitHub()->for($user)->create();
    $token = $user->createToken('test');

    $response = $this->getJson("/api/{$account->id}/github/repos/owner/repo/pulls/1/reviews", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ]);

    $response->assertOk()
        ->assertJsonStructure(['data' => [['id', 'user', 'state', 'body']]]);
});

test('get pull review comments returns comment list', function () {
    $commentData = [['id' => 1, 'body' => 'Nice work', 'user' => ['login' => 'reviewer']]];
    Http::fake(['https://api.github.com/repos/owner/repo/pulls/1/reviews/42/comments*' => Http::response($commentData, 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withGitHub()->for($user)->create();
    $token = $user->createToken('test');

    $response = $this->getJson("/api/{$account->id}/github/repos/owner/repo/pulls/1/reviews/42/comments", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ]);

    $response->assertOk()->assertJsonStructure(['data', 'meta' => ['total_count']]);
});

test('get pull requested reviewers returns raw data', function () {
    $reviewersData = ['users' => [['login' => 'reviewer1']], 'teams' => []];
    Http::fake(['https://api.github.com/repos/owner/repo/pulls/1/requested_reviewers*' => Http::response($reviewersData, 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withGitHub()->for($user)->create();
    $token = $user->createToken('test');

    $response = $this->getJson("/api/{$account->id}/github/repos/owner/repo/pulls/1/requested-reviewers", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ]);

    $response->assertOk()
        ->assertJsonPath('users.0.login', 'reviewer1');
});
