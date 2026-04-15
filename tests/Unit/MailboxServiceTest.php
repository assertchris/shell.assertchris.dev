<?php

use App\Models\Account;
use App\Services\MailboxService;
use DirectoryTree\ImapEngine\Laravel\Facades\Imap;
use DirectoryTree\ImapEngine\MailboxInterface;
use DirectoryTree\ImapEngine\Testing\FakeFolder;
use DirectoryTree\ImapEngine\Testing\FakeMailbox;
use DirectoryTree\ImapEngine\Testing\FakeMessage;
use DirectoryTree\ImapEngine\Testing\FakeMessageQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function makeRfc822(string $subject = 'Test Subject', string $from = 'sender@example.com', string $to = 'recipient@example.com', string $body = 'Hello World'): string
{
    return implode("\r\n", [
        "From: {$from}",
        "To: {$to}",
        'Date: '.now()->format('r'),
        "Subject: {$subject}",
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        '',
        $body,
    ]);
}

test('connect builds and connects an IMAP mailbox', function () {
    $fakeImapMailbox = new FakeMailbox;

    Imap::shouldReceive('build')
        ->once()
        ->andReturn($fakeImapMailbox);

    $service = new MailboxService;
    $account = Account::factory()->create();

    $result = $service->connect($account);

    expect($result)->toBeInstanceOf(MailboxInterface::class);
});

test('getFolders returns folder collection', function () {
    $folders = [
        new FakeFolder('INBOX'),
        new FakeFolder('Sent'),
        new FakeFolder('Drafts'),
    ];

    $fakeMailbox = new FakeMailbox(folders: $folders);

    $service = new MailboxService;
    $result = $service->getFolders($fakeMailbox);

    expect($result)->toHaveCount(3);
    expect($result[0]->path())->toBe('INBOX');
    expect($result[1]->path())->toBe('Sent');
    expect($result[2]->path())->toBe('Drafts');
});

test('getFolder returns a folder by path', function () {
    $folders = [
        new FakeFolder('INBOX'),
        new FakeFolder('Sent'),
    ];

    $fakeMailbox = new FakeMailbox(folders: $folders);

    $service = new MailboxService;

    expect($service->getFolder($fakeMailbox, 'INBOX')->path())->toBe('INBOX');
    expect($service->getFolder($fakeMailbox, 'Sent')->path())->toBe('Sent');
});

test('getFolder returns null for unknown path', function () {
    $fakeMailbox = new FakeMailbox(folders: [new FakeFolder('INBOX')]);

    $service = new MailboxService;

    expect($service->getFolder($fakeMailbox, 'NonExistent'))->toBeNull();
});

test('getMessages returns paginated messages', function () {
    $messages = [
        new FakeMessage(1, contents: makeRfc822('First')),
        new FakeMessage(2, contents: makeRfc822('Second')),
        new FakeMessage(3, contents: makeRfc822('Third')),
    ];

    $folder = new FakeFolder('INBOX', messages: $messages);
    $fakeMailbox = new FakeMailbox(folders: [$folder]);

    $service = new MailboxService;
    $result = $service->getMessages($folder, page: 1, perPage: 2);

    expect($result)->toBeInstanceOf(\DirectoryTree\ImapEngine\Pagination\LengthAwarePaginator::class);
    expect($result->total())->toBe(3);
    expect($result->items())->toHaveCount(2);
});

test('getMessages requests headers and body structure', function () {
    $folder = new FakeFolder('INBOX', messages: []);
    $fakeMailbox = new FakeMailbox(folders: [$folder]);

    $query = Mockery::mock(FakeMessageQuery::class.'[paginate]', [$folder]);
    $query->shouldReceive('paginate')->once()->andReturn(
        new \DirectoryTree\ImapEngine\Pagination\LengthAwarePaginator(collect(), 0, 15)
    );

    $mockFolder = Mockery::mock($folder);
    $mockFolder->shouldReceive('messages')->once()->andReturn($query);

    $service = new MailboxService;
    $service->getMessages($mockFolder);

    expect($query->isFetchingHeaders())->toBeTrue();
    expect($query->isFetchingBodyStructure())->toBeTrue();
    expect($query->isFetchingBody())->toBeFalse();
});

test('getMessage returns a message by UID', function () {
    $messages = [
        new FakeMessage(42, contents: makeRfc822('Important Email')),
    ];

    $folder = new FakeFolder('INBOX', messages: $messages);
    $fakeMailbox = new FakeMailbox(folders: [$folder]);

    $service = new MailboxService;
    $result = $service->getMessage($folder, 42);

    expect($result)->not->toBeNull();
    expect($result->uid())->toBe(42);
    expect($result->subject())->toBe('Important Email');
});

test('getMessage requests headers and body', function () {
    $folder = new FakeFolder('INBOX', messages: []);
    $fakeMailbox = new FakeMailbox(folders: [$folder]);

    $query = Mockery::mock(FakeMessageQuery::class.'[find]', [$folder]);
    $query->shouldReceive('find')->once()->with(1)->andReturnNull();

    $mockFolder = Mockery::mock($folder);
    $mockFolder->shouldReceive('messages')->once()->andReturn($query);

    $service = new MailboxService;
    $service->getMessage($mockFolder, 1);

    expect($query->isFetchingHeaders())->toBeTrue();
    expect($query->isFetchingBody())->toBeTrue();
});

test('getMessage returns null for unknown UID', function () {
    $folder = new FakeFolder('INBOX', messages: []);
    $fakeMailbox = new FakeMailbox(folders: [$folder]);

    $service = new MailboxService;
    $result = $service->getMessage($folder, 999);

    expect($result)->toBeNull();
});
