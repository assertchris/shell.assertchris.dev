<?php

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('list accounts returns only active accounts', function () {
    $user = User::factory()->create();
    Account::factory()->for($user)->create(['name' => 'Active Account']);
    Account::factory()->for($user)->inactive()->create(['name' => 'Inactive Account']);

    $token = $user->createToken('test');

    $this->getJson('/api/accounts', [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Active Account');
});

test('list accounts does not expose credentials', function () {
    $user = User::factory()->create();
    Account::factory()->for($user)->create();

    $token = $user->createToken('test');

    $response = $this->getJson('/api/accounts', [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])->assertSuccessful();

    $data = $response->json('data.0');

    expect($data)->toHaveKeys(['id', 'name', 'mail_used', 'google_calendar_used', 'is_active']);
    expect($data)->not->toHaveKeys(['mail_host', 'mail_port', 'mail_username', 'mail_password', 'mail_encryption', 'google_calendar_access_token', 'google_calendar_refresh_token']);
});

test('list accounts only returns own accounts', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    Account::factory()->for($user)->create(['name' => 'My Account']);
    Account::factory()->for($otherUser)->create(['name' => 'Other Account']);

    $token = $user->createToken('test');

    $this->getJson('/api/accounts', [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'My Account');
});

test('list accounts requires authentication', function () {
    $this->getJson('/api/accounts')
        ->assertUnauthorized();
});
