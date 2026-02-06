<?php

use App\Filament\Resources\AccessTokenResource\Pages\CreateAccessToken;
use App\Filament\Resources\AccessTokenResource\Pages\ListAccessTokens;
use App\Filament\Resources\MailboxResource\Pages\CreateMailbox;
use App\Filament\Resources\MailboxResource\Pages\EditMailbox;
use App\Filament\Resources\MailboxResource\Pages\ListMailboxes;
use App\Models\Mailbox;
use App\Models\User;
use Filament\Pages\Dashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('dashboard renders for authenticated user', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(Dashboard::class)
        ->assertSuccessful();
});

test('access tokens list page renders', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(ListAccessTokens::class)
        ->assertSuccessful();
});

test('access tokens create page renders', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(CreateAccessToken::class)
        ->assertSuccessful();
});

test('mailboxes list page renders', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(ListMailboxes::class)
        ->assertSuccessful();
});

test('mailboxes create page renders', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(CreateMailbox::class)
        ->assertSuccessful();
});

test('mailboxes edit page renders', function () {
    $user = User::factory()->create();
    $mailbox = Mailbox::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test(EditMailbox::class, ['record' => $mailbox->id])
        ->assertSuccessful();
});
