<?php

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function makeAdfBody(array $overrides = []): array
{
    return array_merge([
        'type' => 'doc',
        'version' => 1,
        'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Hello']]],
        ],
    ], $overrides);
}

function makeCommentResponse(array $overrides = []): array
{
    return array_merge([
        'id' => '10001',
        'author' => ['accountId' => 'acc-1', 'displayName' => 'Alice'],
        'body' => makeAdfBody(),
        'created' => '2024-01-01T00:00:00.000+0000',
        'updated' => '2024-01-01T00:00:00.000+0000',
    ], $overrides);
}

test('add comment with valid adf returns 201', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response(makeCommentResponse(), 201)]);

    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->postJson("/api/{$account->id}/jira/issues/PROJ-1/comments", [
        'body' => makeAdfBody(),
    ], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertStatus(201)
        ->assertJsonStructure(['data' => ['id', 'author', 'body']]);
});

test('add comment returns 422 when body is missing', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->postJson("/api/{$account->id}/jira/issues/PROJ-1/comments", [], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['body']);
});

test('add comment returns 422 when body.type is missing', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->postJson("/api/{$account->id}/jira/issues/PROJ-1/comments", [
        'body' => ['version' => 1, 'content' => []],
    ], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['body.type']);
});

test('add comment returns 422 when body.version is missing', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->postJson("/api/{$account->id}/jira/issues/PROJ-1/comments", [
        'body' => ['type' => 'doc', 'content' => []],
    ], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['body.version']);
});

test('add comment returns 422 when body.content is missing', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->postJson("/api/{$account->id}/jira/issues/PROJ-1/comments", [
        'body' => ['type' => 'doc', 'version' => 1],
    ], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['body.content']);
});

test('add comment returns 422 when body is not an array', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->postJson("/api/{$account->id}/jira/issues/PROJ-1/comments", [
        'body' => 'not an array',
    ], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['body']);
});

test('update comment with valid adf returns 200', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response(makeCommentResponse(), 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->putJson("/api/{$account->id}/jira/issues/PROJ-1/comments/10001", [
        'body' => makeAdfBody(),
    ], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJsonStructure(['data' => ['id', 'author', 'body']]);
});

test('delete comment returns 204', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response(null, 204)]);

    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->deleteJson("/api/{$account->id}/jira/issues/PROJ-1/comments/10001", [], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertStatus(204);
});
