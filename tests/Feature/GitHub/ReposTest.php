<?php

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function makeRepoData(array $overrides = []): array
{
    return array_merge([
        'id' => 1,
        'name' => 'my-repo',
        'full_name' => 'owner/my-repo',
        'description' => 'A test repo',
        'private' => false,
        'html_url' => 'https://github.com/owner/my-repo',
        'default_branch' => 'main',
        'updated_at' => '2024-01-01T00:00:00Z',
    ], $overrides);
}

test('list repos returns repo collection', function () {
    Http::fake(['https://api.github.com/user/repos*' => Http::response([makeRepoData()], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withGitHub()->for($user)->create();
    $token = $user->createToken('test');

    $response = $this->getJson("/api/{$account->id}/github/repos", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ]);

    $response->assertOk()
        ->assertJsonStructure(['data' => [['id', 'name', 'full_name', 'html_url']], 'meta' => ['total_count']]);
});

test('list repos includes total_count in meta', function () {
    Http::fake(['https://api.github.com/user/repos*' => Http::response([makeRepoData(), makeRepoData(['id' => 2, 'name' => 'second'])], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withGitHub()->for($user)->create();
    $token = $user->createToken('test');

    $response = $this->getJson("/api/{$account->id}/github/repos", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ]);

    $response->assertOk()->assertJsonPath('meta.total_count', 2);
});

test('list repos forwards pagination params to github api', function () {
    Http::fake(['https://api.github.com/user/repos*' => Http::response([], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withGitHub()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/github/repos?per_page=10&page=2&type=owner", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])->assertOk();

    Http::assertSent(function ($request) {
        parse_str(parse_url($request->url(), PHP_URL_QUERY), $params);

        return $params['per_page'] === '10'
            && $params['page'] === '2'
            && $params['type'] === 'owner';
    });
});

test('get single repo returns repo data', function () {
    Http::fake(['https://api.github.com/repos/owner/my-repo*' => Http::response(makeRepoData(), 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withGitHub()->for($user)->create();
    $token = $user->createToken('test');

    $response = $this->getJson("/api/{$account->id}/github/repos/owner/my-repo", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'my-repo')
        ->assertJsonPath('data.full_name', 'owner/my-repo');
});

test('get repo returns 404 when repo not found', function () {
    Http::fake(['https://api.github.com/repos/owner/missing*' => Http::response([], 404)]);

    $user = User::factory()->create();
    $account = Account::factory()->withGitHub()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/github/repos/owner/missing", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])->assertNotFound();
});
