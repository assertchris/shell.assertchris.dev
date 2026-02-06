<?php

use App\Models\Mailbox;
use App\Models\User;
use App\Services\MailboxService;
use DirectoryTree\ImapEngine\Pagination\LengthAwarePaginator;
use DirectoryTree\ImapEngine\Testing\FakeFolder;
use DirectoryTree\ImapEngine\Testing\FakeMailbox;
use DirectoryTree\ImapEngine\Testing\FakeMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeRfc822Message(
    string $subject = 'Test Subject',
    string $from = 'sender@example.com',
    string $to = 'recipient@example.com',
    ?string $cc = null,
    string $body = 'Hello World',
): string {
    $headers = [
        "From: {$from}",
        "To: {$to}",
    ];

    if ($cc) {
        $headers[] = "Cc: {$cc}";
    }

    $headers[] = 'Date: '.now()->format('r');
    $headers[] = "Subject: {$subject}";
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-Type: text/plain; charset=UTF-8';

    return implode("\r\n", [...$headers, '', $body]);
}

test('list messages returns paginated messages', function () {
    $user = User::factory()->create();
    $mailbox = Mailbox::factory()->for($user)->create();
    $token = $user->createToken('test');

    $messages = collect([
        new FakeMessage(1, contents: makeRfc822Message('First Email')),
        new FakeMessage(2, contents: makeRfc822Message('Second Email')),
    ]);

    $paginator = new LengthAwarePaginator(
        items: $messages,
        total: 2,
        perPage: 15,
        currentPage: 1,
    );

    $fakeMailbox = new FakeMailbox;
    $fakeFolder = new FakeFolder('INBOX');

    $this->mock(MailboxService::class, function ($mock) use ($fakeMailbox, $fakeFolder, $paginator) {
        $mock->shouldReceive('connect')->once()->andReturn($fakeMailbox);
        $mock->shouldReceive('getFolder')->once()->with($fakeMailbox, 'INBOX')->andReturn($fakeFolder);
        $mock->shouldReceive('getMessages')->once()->andReturn($paginator);
    });

    $this->getJson("/api/mailboxes/{$mailbox->id}/folders/INBOX/messages", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.uid', 1)
        ->assertJsonPath('data.0.subject', 'First Email')
        ->assertJsonPath('data.0.has_attachments', false)
        ->assertJsonStructure(['data' => [['uid', 'subject', 'from', 'to', 'date', 'has_attachments']]])
        ->assertJsonPath('meta.total', 2)
        ->assertJsonPath('meta.per_page', 15);
});

test('list messages caps per_page at 50', function () {
    $user = User::factory()->create();
    $mailbox = Mailbox::factory()->for($user)->create();
    $token = $user->createToken('test');

    $paginator = new LengthAwarePaginator(
        items: collect(),
        total: 0,
        perPage: 50,
        currentPage: 1,
    );

    $fakeMailbox = new FakeMailbox;
    $fakeFolder = new FakeFolder('INBOX');

    $this->mock(MailboxService::class, function ($mock) use ($fakeMailbox, $fakeFolder, $paginator) {
        $mock->shouldReceive('connect')->once()->andReturn($fakeMailbox);
        $mock->shouldReceive('getFolder')->once()->andReturn($fakeFolder);
        $mock->shouldReceive('getMessages')
            ->once()
            ->withArgs(fn ($folder, $page, $perPage) => $perPage === 50)
            ->andReturn($paginator);
    });

    $this->getJson("/api/mailboxes/{$mailbox->id}/folders/INBOX/messages?per_page=100", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertSuccessful();
});

test('list messages returns 404 for unknown folder', function () {
    $user = User::factory()->create();
    $mailbox = Mailbox::factory()->for($user)->create();
    $token = $user->createToken('test');

    $fakeMailbox = new FakeMailbox;

    $this->mock(MailboxService::class, function ($mock) use ($fakeMailbox) {
        $mock->shouldReceive('connect')->once()->andReturn($fakeMailbox);
        $mock->shouldReceive('getFolder')->once()->andReturnNull();
    });

    $this->getJson("/api/mailboxes/{$mailbox->id}/folders/NonExistent/messages", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

test('list messages requires authentication', function () {
    $mailbox = Mailbox::factory()->create();

    $this->getJson("/api/mailboxes/{$mailbox->id}/folders/INBOX/messages")
        ->assertUnauthorized();
});

test('show message returns full body', function () {
    $user = User::factory()->create();
    $mailbox = Mailbox::factory()->for($user)->create();
    $token = $user->createToken('test');

    $message = new FakeMessage(42, contents: makeRfc822Message(
        subject: 'Important Email',
        from: 'boss@example.com',
        to: 'me@example.com',
        cc: 'team@example.com',
        body: 'Please review this.',
    ));

    $fakeMailbox = new FakeMailbox;
    $fakeFolder = new FakeFolder('INBOX');

    $this->mock(MailboxService::class, function ($mock) use ($fakeMailbox, $fakeFolder, $message) {
        $mock->shouldReceive('connect')->once()->andReturn($fakeMailbox);
        $mock->shouldReceive('getFolder')->once()->with($fakeMailbox, 'INBOX')->andReturn($fakeFolder);
        $mock->shouldReceive('getMessage')->once()->with($fakeFolder, 42)->andReturn($message);
    });

    $this->getJson("/api/mailboxes/{$mailbox->id}/folders/INBOX/messages/42", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.uid', 42)
        ->assertJsonPath('data.subject', 'Important Email')
        ->assertJsonPath('data.from.email', 'boss@example.com')
        ->assertJsonPath('data.to.0.email', 'me@example.com')
        ->assertJsonPath('data.cc.0.email', 'team@example.com')
        ->assertJsonPath('data.text', 'Please review this.')
        ->assertJsonPath('data.html', null)
        ->assertJsonPath('data.has_attachments', false)
        ->assertJsonPath('data.attachment_count', 0)
        ->assertJsonStructure(['data' => ['uid', 'subject', 'from', 'to', 'date', 'has_attachments', 'cc', 'text', 'html', 'attachment_count']]);
});

test('show message returns 404 for unknown uid', function () {
    $user = User::factory()->create();
    $mailbox = Mailbox::factory()->for($user)->create();
    $token = $user->createToken('test');

    $fakeMailbox = new FakeMailbox;
    $fakeFolder = new FakeFolder('INBOX');

    $this->mock(MailboxService::class, function ($mock) use ($fakeMailbox, $fakeFolder) {
        $mock->shouldReceive('connect')->once()->andReturn($fakeMailbox);
        $mock->shouldReceive('getFolder')->once()->andReturn($fakeFolder);
        $mock->shouldReceive('getMessage')->once()->andReturnNull();
    });

    $this->getJson("/api/mailboxes/{$mailbox->id}/folders/INBOX/messages/999", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

test('show message returns 404 for unknown folder', function () {
    $user = User::factory()->create();
    $mailbox = Mailbox::factory()->for($user)->create();
    $token = $user->createToken('test');

    $fakeMailbox = new FakeMailbox;

    $this->mock(MailboxService::class, function ($mock) use ($fakeMailbox) {
        $mock->shouldReceive('connect')->once()->andReturn($fakeMailbox);
        $mock->shouldReceive('getFolder')->once()->andReturnNull();
    });

    $this->getJson("/api/mailboxes/{$mailbox->id}/folders/NonExistent/messages/1", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

test('show message requires authentication', function () {
    $mailbox = Mailbox::factory()->create();

    $this->getJson("/api/mailboxes/{$mailbox->id}/folders/INBOX/messages/1")
        ->assertUnauthorized();
});
