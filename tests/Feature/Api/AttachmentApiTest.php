<?php

use App\Models\Account;
use App\Models\User;
use App\Services\MailboxService;
use DirectoryTree\ImapEngine\Testing\FakeFolder;
use DirectoryTree\ImapEngine\Testing\FakeMailbox;
use DirectoryTree\ImapEngine\Testing\FakeMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeMultipartMessage(string $filename, string $content, string $mimeType): string
{
    $boundary = 'TEST_BOUNDARY_001';
    $encoded = base64_encode($content);

    $lines = [
        'From: sender@example.com',
        'To: recipient@example.com',
        'Date: '.now()->format('r'),
        'Subject: Message With Attachment',
        'MIME-Version: 1.0',
        "Content-Type: multipart/mixed; boundary=\"{$boundary}\"",
        '',
        "--{$boundary}",
        'Content-Type: text/plain; charset=UTF-8',
        '',
        'This message has an attachment.',
        '',
        "--{$boundary}",
        "Content-Type: {$mimeType}; name=\"{$filename}\"",
        'Content-Transfer-Encoding: base64',
        "Content-Disposition: attachment; filename=\"{$filename}\"",
        '',
        $encoded,
        '',
        "--{$boundary}--",
    ];

    return implode("\r\n", $lines);
}

test('list attachments returns attachment metadata', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $token = $user->createToken('test');

    $message = new FakeMessage(42, contents: makeMultipartMessage(
        filename: 'invoice.pdf',
        content: '%PDF-1.4 fake pdf content',
        mimeType: 'application/pdf',
    ));

    $fakeMailbox = new FakeMailbox;
    $fakeFolder = new FakeFolder('INBOX');

    $this->mock(MailboxService::class, function ($mock) use ($fakeMailbox, $fakeFolder, $message) {
        $mock->shouldReceive('connect')->once()->andReturn($fakeMailbox);
        $mock->shouldReceive('getFolder')->once()->with($fakeMailbox, 'INBOX')->andReturn($fakeFolder);
        $mock->shouldReceive('getMessage')->once()->with($fakeFolder, 42)->andReturn($message);
    });

    $this->getJson("/api/{$account->id}/mailbox/INBOX/messages/42/attachments", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.filename', 'invoice.pdf')
        ->assertJsonPath('data.0.content_type', 'application/pdf')
        ->assertJsonPath('data.0.extension', 'pdf')
        ->assertJsonStructure(['data' => [['filename', 'content_type', 'content_id', 'extension']]]);
});

test('list attachments returns 404 for unknown folder', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $token = $user->createToken('test');

    $fakeMailbox = new FakeMailbox;

    $this->mock(MailboxService::class, function ($mock) use ($fakeMailbox) {
        $mock->shouldReceive('connect')->once()->andReturn($fakeMailbox);
        $mock->shouldReceive('getFolder')->once()->andReturnNull();
    });

    $this->getJson("/api/{$account->id}/mailbox/NonExistent/messages/42/attachments", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

test('list attachments returns 404 for unknown uid', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $token = $user->createToken('test');

    $fakeMailbox = new FakeMailbox;
    $fakeFolder = new FakeFolder('INBOX');

    $this->mock(MailboxService::class, function ($mock) use ($fakeMailbox, $fakeFolder) {
        $mock->shouldReceive('connect')->once()->andReturn($fakeMailbox);
        $mock->shouldReceive('getFolder')->once()->andReturn($fakeFolder);
        $mock->shouldReceive('getMessage')->once()->andReturnNull();
    });

    $this->getJson("/api/{$account->id}/mailbox/INBOX/messages/999/attachments", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

test('list attachments returns 404 for other user account', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $account = Account::factory()->for($otherUser)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/mailbox/INBOX/messages/42/attachments", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

test('list attachments returns 404 when mail_used is false', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['mail_used' => false]);
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/mailbox/INBOX/messages/42/attachments", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

test('list attachments requires authentication', function () {
    $account = Account::factory()->create();

    $this->getJson("/api/{$account->id}/mailbox/INBOX/messages/42/attachments")
        ->assertUnauthorized();
});

test('download attachment streams the binary content', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $token = $user->createToken('test');

    $binaryContent = '%PDF-1.4 fake pdf content';

    $message = new FakeMessage(42, contents: makeMultipartMessage(
        filename: 'invoice.pdf',
        content: $binaryContent,
        mimeType: 'application/pdf',
    ));

    $fakeMailbox = new FakeMailbox;
    $fakeFolder = new FakeFolder('INBOX');

    $this->mock(MailboxService::class, function ($mock) use ($fakeMailbox, $fakeFolder, $message) {
        $mock->shouldReceive('connect')->once()->andReturn($fakeMailbox);
        $mock->shouldReceive('getFolder')->once()->with($fakeMailbox, 'INBOX')->andReturn($fakeFolder);
        $mock->shouldReceive('getMessage')->once()->with($fakeFolder, 42)->andReturn($message);
    });

    $this->getJson("/api/{$account->id}/mailbox/INBOX/messages/42/attachments/0", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertSuccessful()
        ->assertDownload('invoice.pdf');
});

test('download attachment returns 404 for out-of-bounds index', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $token = $user->createToken('test');

    $message = new FakeMessage(42, contents: makeMultipartMessage(
        filename: 'invoice.pdf',
        content: 'content',
        mimeType: 'application/pdf',
    ));

    $fakeMailbox = new FakeMailbox;
    $fakeFolder = new FakeFolder('INBOX');

    $this->mock(MailboxService::class, function ($mock) use ($fakeMailbox, $fakeFolder, $message) {
        $mock->shouldReceive('connect')->once()->andReturn($fakeMailbox);
        $mock->shouldReceive('getFolder')->once()->andReturn($fakeFolder);
        $mock->shouldReceive('getMessage')->once()->andReturn($message);
    });

    $this->getJson("/api/{$account->id}/mailbox/INBOX/messages/42/attachments/99", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

test('download attachment returns 404 for unknown folder', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $token = $user->createToken('test');

    $fakeMailbox = new FakeMailbox;

    $this->mock(MailboxService::class, function ($mock) use ($fakeMailbox) {
        $mock->shouldReceive('connect')->once()->andReturn($fakeMailbox);
        $mock->shouldReceive('getFolder')->once()->andReturnNull();
    });

    $this->getJson("/api/{$account->id}/mailbox/NonExistent/messages/42/attachments/0", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

test('download attachment returns 404 for unknown uid', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create();
    $token = $user->createToken('test');

    $fakeMailbox = new FakeMailbox;
    $fakeFolder = new FakeFolder('INBOX');

    $this->mock(MailboxService::class, function ($mock) use ($fakeMailbox, $fakeFolder) {
        $mock->shouldReceive('connect')->once()->andReturn($fakeMailbox);
        $mock->shouldReceive('getFolder')->once()->andReturn($fakeFolder);
        $mock->shouldReceive('getMessage')->once()->andReturnNull();
    });

    $this->getJson("/api/{$account->id}/mailbox/INBOX/messages/999/attachments/0", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

test('download attachment returns 404 for other user account', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $account = Account::factory()->for($otherUser)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/mailbox/INBOX/messages/42/attachments/0", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

test('download attachment returns 404 when mail_used is false', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['mail_used' => false]);
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/mailbox/INBOX/messages/42/attachments/0", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

test('download attachment requires authentication', function () {
    $account = Account::factory()->create();

    $this->getJson("/api/{$account->id}/mailbox/INBOX/messages/42/attachments/0")
        ->assertUnauthorized();
});
