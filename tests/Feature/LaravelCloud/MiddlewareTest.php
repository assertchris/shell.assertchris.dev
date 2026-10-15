<?php

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('laravel cloud routes require authentication', function () {
    $account = Account::factory()->withLaravelCloud()->create();

    $this->getJson("/api/{$account->id}/laravel-cloud/applications")
        ->assertUnauthorized();
});

test('laravel cloud routes return 404 when account belongs to another user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $account = Account::factory()->withLaravelCloud()->for($otherUser)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/laravel-cloud/applications", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

test('laravel cloud routes return 404 when laravel_cloud_used is false', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create([
        'laravel_cloud_used' => false,
        'laravel_cloud_api_token' => null,
    ]);
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/laravel-cloud/applications", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

test('laravel cloud routes return 404 when laravel_cloud_used is true but token is null', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create([
        'laravel_cloud_used' => true,
        'laravel_cloud_api_token' => null,
    ]);
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/laravel-cloud/applications", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

// TODO (Phase 7): Add test that a fully-connected account can reach a real Laravel Cloud endpoint (200).
// Routes don't exist until Phase 4; a representative GET endpoint will be used once available.
