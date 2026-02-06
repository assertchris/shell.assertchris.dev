<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

test('creating a token returns a plain-text token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token');

    expect($token->plainTextToken)->toBeString()->not->toBeEmpty();
});

test('token is stored as a SHA-256 hash', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token');

    $id = explode('|', $token->plainTextToken)[0];
    $plainToken = explode('|', $token->plainTextToken)[1];

    $record = PersonalAccessToken::find($id);

    expect($record->token)->toBe(hash('sha256', $plainToken));
});

test('deleting a token revokes access', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token');
    $plainTextToken = $token->plainTextToken;

    $token->accessToken->delete();

    $this->getJson('/api/status', [
        'Authorization' => 'Bearer '.$plainTextToken,
    ])
        ->assertUnauthorized();
});
