<?php

use App\Filament\Resources\DashboardMessageResource\Pages\ListDashboardMessages;
use App\Filament\Resources\DashboardMessageResource\Pages\ViewDashboardMessage;
use App\Models\DashboardMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('admin can view message list page', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(ListDashboardMessages::class)
        ->assertSuccessful();
});

test('admin can see their messages in the list', function () {
    $user = User::factory()->create();
    DashboardMessage::factory()->for($user)->create(['title' => 'Important Update']);

    $this->actingAs($user);

    Livewire::test(ListDashboardMessages::class)
        ->assertSee('Important Update');
});

test('admin cannot see other users messages', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    DashboardMessage::factory()->for($other)->create(['title' => 'Secret Message']);

    $this->actingAs($user);

    Livewire::test(ListDashboardMessages::class)
        ->assertDontSee('Secret Message');
});

test('list excludes archived messages by default', function () {
    $user = User::factory()->create();
    DashboardMessage::factory()->for($user)->create(['title' => 'Active Message']);
    DashboardMessage::factory()->for($user)->archived()->create(['title' => 'Archived Message']);

    $this->actingAs($user);

    Livewire::test(ListDashboardMessages::class)
        ->assertSee('Active Message')
        ->assertDontSee('Archived Message');
});

test('admin can view a message', function () {
    $user = User::factory()->create();
    $message = DashboardMessage::factory()->for($user)->create([
        'title' => 'Test Title',
        'body' => '<p>Test body content</p>',
    ]);

    $this->actingAs($user);

    Livewire::test(ViewDashboardMessage::class, ['record' => $message->id])
        ->assertSuccessful()
        ->assertSee('Test Title');
});

test('viewing a message auto-marks it as read', function () {
    $user = User::factory()->create();
    $message = DashboardMessage::factory()->for($user)->create();

    expect($message->read_at)->toBeNull();

    $this->actingAs($user);

    Livewire::test(ViewDashboardMessage::class, ['record' => $message->id]);

    expect($message->fresh()->read_at)->not->toBeNull();
});

test('viewing an already-read message does not update read_at', function () {
    $user = User::factory()->create();
    $readAt = now()->subHour();
    $message = DashboardMessage::factory()->for($user)->create(['read_at' => $readAt]);

    $this->actingAs($user);

    Livewire::test(ViewDashboardMessage::class, ['record' => $message->id]);

    expect($message->fresh()->read_at->timestamp)->toBe($readAt->timestamp);
});

test('toggle read action marks message as unread', function () {
    $user = User::factory()->create();
    $message = DashboardMessage::factory()->for($user)->read()->create();

    $this->actingAs($user);

    Livewire::test(ViewDashboardMessage::class, ['record' => $message->id])
        ->callAction('toggle_read');

    expect($message->fresh()->read_at)->toBeNull();
});

test('toggle archive action archives message', function () {
    $user = User::factory()->create();
    $message = DashboardMessage::factory()->for($user)->create();

    $this->actingAs($user);

    Livewire::test(ViewDashboardMessage::class, ['record' => $message->id])
        ->callAction('toggle_archive');

    expect($message->fresh()->archived_at)->not->toBeNull();
});

test('toggle archive action unarchives message', function () {
    $user = User::factory()->create();
    $message = DashboardMessage::factory()->for($user)->archived()->create();

    $this->actingAs($user);

    Livewire::test(ViewDashboardMessage::class, ['record' => $message->id])
        ->callAction('toggle_archive');

    expect($message->fresh()->archived_at)->toBeNull();
});

test('admin can delete a message', function () {
    $user = User::factory()->create();
    $message = DashboardMessage::factory()->for($user)->create();

    $this->actingAs($user);

    Livewire::test(ViewDashboardMessage::class, ['record' => $message->id])
        ->callAction('delete');

    expect(DashboardMessage::find($message->id))->toBeNull();
});

test('list page has no create action', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(ListDashboardMessages::class)
        ->assertActionDoesNotExist('create');
});
