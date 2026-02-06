<?php

use App\Models\Mailbox;
use App\Models\User;
use App\Services\MailboxService;
use DirectoryTree\ImapEngine\Testing\FakeFolder;
use DirectoryTree\ImapEngine\Testing\FakeMailbox;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('list mailboxes returns only active mailboxes', function () {
    $user = User::factory()->create();
    Mailbox::factory()->for($user)->create(['name' => 'Active Mailbox']);
    Mailbox::factory()->for($user)->inactive()->create(['name' => 'Inactive Mailbox']);

    $token = $user->createToken('test');

    $this->getJson('/api/mailboxes', [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Active Mailbox');
});

test('list mailboxes does not expose credentials', function () {
    $user = User::factory()->create();
    Mailbox::factory()->for($user)->create();

    $token = $user->createToken('test');

    $response = $this->getJson('/api/mailboxes', [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])->assertSuccessful();

    $data = $response->json('data.0');

    expect($data)->toHaveKeys(['id', 'name']);
    expect($data)->not->toHaveKeys(['host', 'port', 'username', 'password', 'encryption']);
});

test('list mailboxes only returns own mailboxes', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    Mailbox::factory()->for($user)->create(['name' => 'My Mailbox']);
    Mailbox::factory()->for($otherUser)->create(['name' => 'Other Mailbox']);

    $token = $user->createToken('test');

    $this->getJson('/api/mailboxes', [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'My Mailbox');
});

test('list mailboxes requires authentication', function () {
    $this->getJson('/api/mailboxes')
        ->assertUnauthorized();
});

test('list folders returns IMAP folders', function () {
    $user = User::factory()->create();
    $mailbox = Mailbox::factory()->for($user)->create();
    $token = $user->createToken('test');

    $fakeMailbox = new FakeMailbox(folders: [
        new FakeFolder('INBOX'),
        new FakeFolder('Sent'),
    ]);

    $this->mock(MailboxService::class, function ($mock) use ($fakeMailbox) {
        $mock->shouldReceive('connect')->once()->andReturn($fakeMailbox);
        $mock->shouldReceive('getFolders')->once()->with($fakeMailbox)->andReturn($fakeMailbox->folders()->get());
    });

    $this->getJson("/api/mailboxes/{$mailbox->id}/folders", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.path', 'INBOX')
        ->assertJsonPath('data.0.name', 'INBOX')
        ->assertJsonPath('data.1.path', 'Sent')
        ->assertJsonPath('data.1.name', 'Sent');
});

test('list folders returns 404 for other user mailbox', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $mailbox = Mailbox::factory()->for($otherUser)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/mailboxes/{$mailbox->id}/folders", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

test('list folders returns 503 on connection failure', function () {
    $user = User::factory()->create();
    $mailbox = Mailbox::factory()->for($user)->create();
    $token = $user->createToken('test');

    $this->mock(MailboxService::class, function ($mock) {
        $mock->shouldReceive('connect')->once()->andThrow(new \RuntimeException('Connection refused'));
    });

    $this->getJson("/api/mailboxes/{$mailbox->id}/folders", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertServiceUnavailable();
});

test('list folders requires authentication', function () {
    $mailbox = Mailbox::factory()->create();

    $this->getJson("/api/mailboxes/{$mailbox->id}/folders")
        ->assertUnauthorized();
});
