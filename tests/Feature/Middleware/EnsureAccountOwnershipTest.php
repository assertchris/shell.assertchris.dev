<?php

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('account ownership middleware passes when user owns account', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/mailbox", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertStatus(503); // Gets past ownership check, fails at mailbox connection
});

test('account ownership middleware returns 404 when user does not own account', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $account = Account::factory()->for($otherUser)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/mailbox", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

test('account ownership middleware passes for routes without account parameter', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test');

    $this->getJson('/api/status', [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk();
});

test('account ownership middleware passes for accounts list route', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test');

    $this->getJson('/api/accounts', [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk();
});
