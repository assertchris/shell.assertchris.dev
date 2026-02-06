<?php

use App\Filament\Resources\MailboxResource\Pages\CreateMailbox;
use App\Filament\Resources\MailboxResource\Pages\EditMailbox;
use App\Filament\Resources\MailboxResource\Pages\ListMailboxes;
use App\Models\Mailbox;
use App\Models\User;
use DirectoryTree\ImapEngine\Laravel\Facades\Imap;
use DirectoryTree\ImapEngine\MailboxInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('admin can view mailbox list page', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(ListMailboxes::class)
        ->assertSuccessful();
});

test('admin can see their mailboxes in the list', function () {
    $user = User::factory()->create();
    Mailbox::factory()->create(['user_id' => $user->id, 'name' => 'My Gmail']);

    $this->actingAs($user);

    Livewire::test(ListMailboxes::class)
        ->assertSee('My Gmail');
});

test('admin cannot see other users mailboxes', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    Mailbox::factory()->create(['user_id' => $other->id, 'name' => 'Other Mailbox']);

    $this->actingAs($user);

    Livewire::test(ListMailboxes::class)
        ->assertDontSee('Other Mailbox');
});

test('admin can render create mailbox page', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(CreateMailbox::class)
        ->assertSuccessful();
});

test('admin can create a mailbox', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(CreateMailbox::class)
        ->fillForm([
            'name' => 'Work Email',
            'host' => 'imap.example.com',
            'port' => 993,
            'encryption' => 'ssl',
            'username' => 'user@example.com',
            'password' => 'secret-password',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $mailbox = Mailbox::where('name', 'Work Email')->first();

    expect($mailbox)->not->toBeNull()
        ->and($mailbox->user_id)->toBe($user->id)
        ->and($mailbox->host)->toBe('imap.example.com');
});

test('admin can render edit mailbox page', function () {
    $user = User::factory()->create();
    $mailbox = Mailbox::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test(EditMailbox::class, ['record' => $mailbox->id])
        ->assertSuccessful();
});

test('admin can edit a mailbox', function () {
    $user = User::factory()->create();
    $mailbox = Mailbox::factory()->create(['user_id' => $user->id, 'name' => 'Old Name']);

    $this->actingAs($user);

    Livewire::test(EditMailbox::class, ['record' => $mailbox->id])
        ->fillForm([
            'name' => 'New Name',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($mailbox->fresh()->name)->toBe('New Name');
});

test('admin can delete a mailbox', function () {
    $user = User::factory()->create();
    $mailbox = Mailbox::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test(ListMailboxes::class)
        ->callTableAction('delete', $mailbox->id);

    expect(Mailbox::find($mailbox->id))->toBeNull();
});

test('test connection action shows success on valid connection', function () {
    $user = User::factory()->create();
    $mailbox = Mailbox::factory()->create(['user_id' => $user->id]);

    $fakeMailbox = Mockery::mock(MailboxInterface::class);
    $fakeMailbox->shouldReceive('connect')->once();
    $fakeMailbox->shouldReceive('disconnect')->once();

    Imap::shouldReceive('build')
        ->once()
        ->with($mailbox->toImapConfig())
        ->andReturn($fakeMailbox);

    $this->actingAs($user);

    Livewire::test(ListMailboxes::class)
        ->callTableAction('test_connection', $mailbox->id)
        ->assertNotified('Connection successful');
});

test('test connection action shows failure on invalid connection', function () {
    $user = User::factory()->create();
    $mailbox = Mailbox::factory()->create(['user_id' => $user->id]);

    Imap::shouldReceive('build')
        ->once()
        ->andThrow(new \RuntimeException('Connection refused'));

    $this->actingAs($user);

    Livewire::test(ListMailboxes::class)
        ->callTableAction('test_connection', $mailbox->id)
        ->assertNotified('Connection failed');
});

test('edit page has test connection header action', function () {
    $user = User::factory()->create();
    $mailbox = Mailbox::factory()->create(['user_id' => $user->id]);

    $fakeMailbox = Mockery::mock(MailboxInterface::class);
    $fakeMailbox->shouldReceive('connect')->once();
    $fakeMailbox->shouldReceive('disconnect')->once();

    Imap::shouldReceive('build')
        ->once()
        ->with($mailbox->toImapConfig())
        ->andReturn($fakeMailbox);

    $this->actingAs($user);

    Livewire::test(EditMailbox::class, ['record' => $mailbox->id])
        ->callAction('test_connection')
        ->assertNotified('Connection successful');
});

test('edit page save notification prompts to test connection', function () {
    $user = User::factory()->create();
    $mailbox = Mailbox::factory()->create(['user_id' => $user->id, 'name' => 'Test Box']);

    $this->actingAs($user);

    Livewire::test(EditMailbox::class, ['record' => $mailbox->id])
        ->fillForm(['name' => 'Updated Box'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Mailbox saved');
});
