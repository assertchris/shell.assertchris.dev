<?php

use App\Filament\Resources\AccountResource\Pages\CreateAccount;
use App\Filament\Resources\AccountResource\Pages\EditAccount;
use App\Filament\Resources\AccountResource\Pages\ListAccounts;
use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('admin can view account list page', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(ListAccounts::class)
        ->assertSuccessful();
});

test('admin can see their accounts in the list', function () {
    $user = User::factory()->create();
    Account::factory()->create(['user_id' => $user->id, 'name' => 'My Work Account']);

    $this->actingAs($user);

    Livewire::test(ListAccounts::class)
        ->assertSee('My Work Account');
});

test('admin cannot see other users accounts', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    Account::factory()->create(['user_id' => $other->id, 'name' => 'Other Account']);

    $this->actingAs($user);

    Livewire::test(ListAccounts::class)
        ->assertDontSee('Other Account');
});

test('admin can render create account page', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(CreateAccount::class)
        ->assertSuccessful();
});

test('admin can create an account', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(CreateAccount::class)
        ->fillForm([
            'name' => 'Work Email',
            'mail_used' => true,
            'mail_host' => 'imap.example.com',
            'mail_port' => 993,
            'mail_encryption' => 'ssl',
            'mail_username' => 'user@example.com',
            'mail_password' => 'secret-password',
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $account = Account::where('name', 'Work Email')->first();

    expect($account)->not->toBeNull()
        ->and($account->user_id)->toBe($user->id)
        ->and($account->mail_host)->toBe('imap.example.com');
});

test('admin can render edit account page', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test(EditAccount::class, ['record' => $account->id])
        ->assertSuccessful();
});

test('admin can edit an account', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id, 'name' => 'Old Name']);

    $this->actingAs($user);

    Livewire::test(EditAccount::class, ['record' => $account->id])
        ->fillForm([
            'name' => 'New Name',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($account->fresh()->name)->toBe('New Name');
});

test('admin can delete an account', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test(ListAccounts::class)
        ->callTableAction('delete', $account->id);

    expect(Account::find($account->id))->toBeNull();
});
