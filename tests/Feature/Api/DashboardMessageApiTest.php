<?php

use App\Models\DashboardMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('create dashboard message with valid data', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test-assistant');

    $this->postJson('/api/dashboard/messages', [
        'title' => 'Urgent Email Summary',
        'body' => '<p>You have 3 urgent emails.</p>',
        'priority' => 'high',
    ], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Urgent Email Summary')
        ->assertJsonPath('data.body', '<p>You have 3 urgent emails.</p>')
        ->assertJsonPath('data.priority', 'high')
        ->assertJsonPath('data.source', 'test-assistant')
        ->assertJsonPath('data.is_read', false)
        ->assertJsonPath('data.is_archived', false);

    $this->assertDatabaseHas('dashboard_messages', [
        'user_id' => $user->id,
        'title' => 'Urgent Email Summary',
        'priority' => 'high',
    ]);
});

test('create dashboard message defaults priority to normal', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test');

    $this->postJson('/api/dashboard/messages', [
        'title' => 'A message',
        'body' => 'Some content',
    ], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertCreated()
        ->assertJsonPath('data.priority', 'normal');
});

test('create dashboard message validates required fields', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test');

    $this->postJson('/api/dashboard/messages', [], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title', 'body']);
});

test('create dashboard message validates priority enum', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test');

    $this->postJson('/api/dashboard/messages', [
        'title' => 'Test',
        'body' => 'Content',
        'priority' => 'invalid',
    ], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['priority']);
});

test('create dashboard message captures source token info', function () {
    $user = User::factory()->create();
    $token = $user->createToken('my-assistant');

    $this->postJson('/api/dashboard/messages', [
        'title' => 'Test',
        'body' => 'Content',
    ], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])->assertCreated();

    $message = DashboardMessage::first();

    expect($message->source)->toBe('my-assistant')
        ->and($message->source_token_id)->toBe($token->accessToken->id);
});

test('list dashboard messages returns paginated results', function () {
    $user = User::factory()->create();
    DashboardMessage::factory()->for($user)->count(3)->create();
    $token = $user->createToken('test');

    $this->getJson('/api/dashboard/messages', [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertSuccessful()
        ->assertJsonCount(3, 'data');
});

test('list dashboard messages only returns own messages', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    DashboardMessage::factory()->for($user)->create(['title' => 'My message']);
    DashboardMessage::factory()->for($otherUser)->create(['title' => 'Other message']);

    $token = $user->createToken('test');

    $this->getJson('/api/dashboard/messages', [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'My message');
});

test('list dashboard messages excludes archived by default', function () {
    $user = User::factory()->create();
    DashboardMessage::factory()->for($user)->create(['title' => 'Active']);
    DashboardMessage::factory()->for($user)->archived()->create(['title' => 'Archived']);
    $token = $user->createToken('test');

    $this->getJson('/api/dashboard/messages', [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Active');
});

test('list dashboard messages includes archived when requested', function () {
    $user = User::factory()->create();
    DashboardMessage::factory()->for($user)->create();
    DashboardMessage::factory()->for($user)->archived()->create();
    $token = $user->createToken('test');

    $this->getJson('/api/dashboard/messages?include_archived=1', [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertSuccessful()
        ->assertJsonCount(2, 'data');
});

test('list dashboard messages filters unread only', function () {
    $user = User::factory()->create();
    DashboardMessage::factory()->for($user)->create(['title' => 'Unread']);
    DashboardMessage::factory()->for($user)->read()->create(['title' => 'Read']);
    $token = $user->createToken('test');

    $this->getJson('/api/dashboard/messages?unread_only=1', [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Unread');
});

test('update dashboard message marks as read', function () {
    $user = User::factory()->create();
    $message = DashboardMessage::factory()->for($user)->create();
    $token = $user->createToken('test');

    $this->patchJson("/api/dashboard/messages/{$message->id}", [
        'is_read' => true,
    ], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.is_read', true);

    expect($message->fresh()->read_at)->not->toBeNull();
});

test('update dashboard message marks as unread', function () {
    $user = User::factory()->create();
    $message = DashboardMessage::factory()->for($user)->read()->create();
    $token = $user->createToken('test');

    $this->patchJson("/api/dashboard/messages/{$message->id}", [
        'is_read' => false,
    ], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.is_read', false);

    expect($message->fresh()->read_at)->toBeNull();
});

test('update dashboard message marks as archived', function () {
    $user = User::factory()->create();
    $message = DashboardMessage::factory()->for($user)->create();
    $token = $user->createToken('test');

    $this->patchJson("/api/dashboard/messages/{$message->id}", [
        'is_archived' => true,
    ], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.is_archived', true);

    expect($message->fresh()->archived_at)->not->toBeNull();
});

test('update dashboard message returns 404 for other user message', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $message = DashboardMessage::factory()->for($otherUser)->create();
    $token = $user->createToken('test');

    $this->patchJson("/api/dashboard/messages/{$message->id}", [
        'is_read' => true,
    ], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

test('dashboard message endpoints require authentication', function () {
    $this->getJson('/api/dashboard/messages')->assertUnauthorized();
    $this->postJson('/api/dashboard/messages')->assertUnauthorized();
    $this->patchJson('/api/dashboard/messages/1')->assertUnauthorized();
});
