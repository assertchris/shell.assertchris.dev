<?php

use App\Models\Mailbox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('mailbox can be created via factory', function () {
    $mailbox = Mailbox::factory()->create();

    expect($mailbox)->toBeInstanceOf(Mailbox::class)
        ->and($mailbox->exists)->toBeTrue();
});

test('mailbox password is encrypted at rest', function () {
    $mailbox = Mailbox::factory()->create(['password' => 'secret-password']);

    $raw = DB::table('mailboxes')->where('id', $mailbox->id)->value('password');

    expect($raw)->not->toBe('secret-password')
        ->and($mailbox->fresh()->password)->toBe('secret-password');
});

test('mailbox is_active is cast to boolean', function () {
    $mailbox = Mailbox::factory()->create(['is_active' => 1]);

    expect($mailbox->fresh()->is_active)->toBeTrue()->toBeBool();
});

test('toImapConfig returns correct array', function () {
    $mailbox = Mailbox::factory()->create([
        'host' => 'imap.example.com',
        'port' => 993,
        'encryption' => 'ssl',
        'username' => 'user@example.com',
        'password' => 'secret',
    ]);

    $config = $mailbox->toImapConfig();

    expect($config)->toBe([
        'host' => 'imap.example.com',
        'port' => 993,
        'encryption' => 'ssl',
        'username' => 'user@example.com',
        'password' => 'secret',
    ]);
});

test('mailbox belongs to a user', function () {
    $user = User::factory()->create();
    $mailbox = Mailbox::factory()->create(['user_id' => $user->id]);

    expect($mailbox->user->id)->toBe($user->id);
});

test('user has many mailboxes', function () {
    $user = User::factory()->create();
    Mailbox::factory()->count(3)->create(['user_id' => $user->id]);

    expect($user->mailboxes)->toHaveCount(3);
});
