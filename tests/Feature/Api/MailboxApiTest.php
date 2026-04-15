<?php

use App\Models\Account;
use App\Models\User;
use App\Services\MailboxService;
use DirectoryTree\ImapEngine\Testing\FakeFolder;
use DirectoryTree\ImapEngine\Testing\FakeMailbox;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('list folders returns IMAP folders', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $token = $user->createToken('test');

    $fakeMailbox = new FakeMailbox(folders: [
        new FakeFolder('INBOX'),
        new FakeFolder('Sent'),
    ]);

    $this->mock(MailboxService::class, function ($mock) use ($fakeMailbox) {
        $mock->shouldReceive('connect')->once()->andReturn($fakeMailbox);
        $mock->shouldReceive('getFolders')->once()->with($fakeMailbox)->andReturn($fakeMailbox->folders()->get());
    });

    $this->getJson("/api/{$account->id}/mailbox", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.path', 'INBOX')
        ->assertJsonPath('data.0.name', 'INBOX')
        ->assertJsonPath('data.1.path', 'Sent')
        ->assertJsonPath('data.1.name', 'Sent');
});

test('list folders returns 404 for other user account', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $account = Account::factory()->for($otherUser)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/mailbox", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

test('list folders returns 404 when mail_used is false', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['mail_used' => false]);
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/mailbox", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

test('list folders returns 503 on connection failure', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $token = $user->createToken('test');

    $this->mock(MailboxService::class, function ($mock) {
        $mock->shouldReceive('connect')->once()->andThrow(new \RuntimeException('Connection refused'));
    });

    $this->getJson("/api/{$account->id}/mailbox", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertServiceUnavailable();
});

test('list folders requires authentication', function () {
    $account = Account::factory()->create();

    $this->getJson("/api/{$account->id}/mailbox")
        ->assertUnauthorized();
});
