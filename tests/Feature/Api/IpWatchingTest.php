<?php

use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;

uses(RefreshDatabase::class);

test('first request records the IP address', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token');

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->getJson('/api/status', ['Authorization' => 'Bearer '.$token->plainTextToken])
        ->assertSuccessful();

    $accessToken = PersonalAccessToken::find($token->accessToken->id);

    expect($accessToken->last_ip_address)->toBe('10.0.0.1')
        ->and($accessToken->ip_changed_at)->toBeNull();
});

test('same IP on subsequent requests does not write to database', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token');
    $headers = ['Authorization' => 'Bearer '.$token->plainTextToken];

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->getJson('/api/status', $headers)
        ->assertSuccessful();

    $accessToken = PersonalAccessToken::find($token->accessToken->id);
    $originalUpdatedAt = $accessToken->updated_at;

    $this->travel(1)->seconds();

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->getJson('/api/status', $headers)
        ->assertSuccessful();

    $accessToken->refresh();

    expect($accessToken->updated_at->toDateTimeString())->toBe($originalUpdatedAt->toDateTimeString());
});

test('first IP change of the day is allowed', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token');
    $headers = ['Authorization' => 'Bearer '.$token->plainTextToken];

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->getJson('/api/status', $headers)
        ->assertSuccessful();

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
        ->getJson('/api/status', $headers)
        ->assertSuccessful();

    $accessToken = PersonalAccessToken::find($token->accessToken->id);

    expect($accessToken->last_ip_address)->toBe('10.0.0.2');
});

test('second IP change in the same day returns 403', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token');
    $headers = ['Authorization' => 'Bearer '.$token->plainTextToken];

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->getJson('/api/status', $headers)
        ->assertSuccessful();

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
        ->getJson('/api/status', $headers)
        ->assertSuccessful();

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.3'])
        ->getJson('/api/status', $headers)
        ->assertForbidden();
});

test('returning to original IP after a change is also blocked', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token');
    $headers = ['Authorization' => 'Bearer '.$token->plainTextToken];

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->getJson('/api/status', $headers)
        ->assertSuccessful();

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
        ->getJson('/api/status', $headers)
        ->assertSuccessful();

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->getJson('/api/status', $headers)
        ->assertForbidden();
});

test('next calendar day resets IP change allowance', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-token');
    $headers = ['Authorization' => 'Bearer '.$token->plainTextToken];

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->getJson('/api/status', $headers)
        ->assertSuccessful();

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
        ->getJson('/api/status', $headers)
        ->assertSuccessful();

    $this->travel(1)->days();

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.3'])
        ->getJson('/api/status', $headers)
        ->assertSuccessful();

    $accessToken = PersonalAccessToken::find($token->accessToken->id);

    expect($accessToken->last_ip_address)->toBe('10.0.0.3');
});

test('session-based auth is not affected by IP binding', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/status')
        ->assertSuccessful();
});

test('blocked request is logged', function () {
    Log::spy();

    $user = User::factory()->create();
    $token = $user->createToken('test-token');
    $headers = ['Authorization' => 'Bearer '.$token->plainTextToken];

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.1'])
        ->getJson('/api/status', $headers);

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
        ->getJson('/api/status', $headers);

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.3'])
        ->getJson('/api/status', $headers)
        ->assertForbidden();

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message) => $message === 'Token used from multiple IPs in one day')
        ->once();
});
