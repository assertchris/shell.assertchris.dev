<?php

use App\Filament\Resources\AccountResource\Pages\CreateAccount;
use App\Filament\Resources\AccountResource\Pages\EditAccount;
use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('admin can create an account with github enabled and a pat', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(CreateAccount::class)
        ->fillForm([
            'name' => 'GitHub Account',
            'mail_used' => false,
            'github_used' => true,
            'github_pat' => 'ghp_test_token',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $account = Account::where('name', 'GitHub Account')->first();

    expect($account)->not->toBeNull()
        ->and($account->github_used)->toBeTrue()
        ->and($account->github_pat)->toBe('ghp_test_token');
});

test('admin can edit an account and leave pat blank to preserve existing pat', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withGitHub()->create([
        'user_id' => $user->id,
        'github_pat' => 'original_pat',
    ]);

    $this->actingAs($user);

    Livewire::test(EditAccount::class, ['record' => $account->id])
        ->fillForm([
            'github_used' => true,
            'github_pat' => '',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($account->fresh()->github_pat)->toBe('original_pat');
});

test('admin can edit an account and supply a new pat', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withGitHub()->create([
        'user_id' => $user->id,
        'github_pat' => 'old_pat',
    ]);

    $this->actingAs($user);

    Livewire::test(EditAccount::class, ['record' => $account->id])
        ->fillForm([
            'github_used' => true,
            'github_pat' => 'new_pat',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($account->fresh()->github_pat)->toBe('new_pat');
});
