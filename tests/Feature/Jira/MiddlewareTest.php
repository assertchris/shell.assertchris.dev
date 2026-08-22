<?php

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('jira routes require authentication', function () {
    $account = Account::factory()->withJira()->create();

    $this->getJson("/api/{$account->id}/jira/projects")
        ->assertUnauthorized();
});

test('jira routes return 404 when account belongs to another user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $account = Account::factory()->withJira()->for($otherUser)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/jira/projects", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

test('jira routes return 404 when jira_used is false', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create([
        'jira_used' => false,
        'jira_base_url' => null,
        'jira_email' => null,
        'jira_api_token' => null,
    ]);
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/jira/projects", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

test('jira routes return 404 when jira_used is true but credentials are null', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create([
        'jira_used' => true,
        'jira_base_url' => null,
        'jira_email' => null,
        'jira_api_token' => null,
    ]);
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/jira/projects", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

test('jira routes return 200 when fully connected', function () {
    Http::fake(['https://test.atlassian.net/*' => Http::response(['values' => []], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withJira()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/jira/projects", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk();
});
