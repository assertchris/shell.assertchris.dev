<?php

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('github routes require authentication', function () {
    $account = Account::factory()->withGitHub()->create();

    $this->getJson("/api/{$account->id}/github/repos")
        ->assertUnauthorized();
});

test('github routes return 404 when account belongs to another user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $account = Account::factory()->withGitHub()->for($otherUser)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/github/repos", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

test('github routes return 404 when github_used is false', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['github_used' => false, 'github_pat' => null]);
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/github/repos", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

test('github routes return 404 when github_pat is null even though github_used is true', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['github_used' => true, 'github_pat' => null]);
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/github/repos", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

test('github routes return 200 when connected', function () {
    Http::fake(['https://api.github.com/*' => Http::response([], 200)]);

    $user = User::factory()->create();
    $account = Account::factory()->withGitHub()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/github/repos", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk();
});
