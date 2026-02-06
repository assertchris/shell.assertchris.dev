<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('status endpoint returns ok with valid token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token');

    $this->getJson('/api/status', [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertOk()
        ->assertJson(['status' => 'ok']);
});

test('status endpoint returns 401 without token', function () {
    $this->getJson('/api/status')
        ->assertUnauthorized();
});

test('status endpoint returns 401 with invalid token', function () {
    $this->getJson('/api/status', [
        'Authorization' => 'Bearer invalid-token',
    ])
        ->assertUnauthorized();
});
