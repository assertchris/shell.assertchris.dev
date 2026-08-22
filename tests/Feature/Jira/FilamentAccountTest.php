<?php

use App\Filament\Resources\AccountResource\Pages\CreateAccount;
use App\Filament\Resources\AccountResource\Pages\EditAccount;
use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('admin can create an account with jira enabled and credentials', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(CreateAccount::class)
        ->fillForm([
            'name' => 'Jira Account',
            'mail_used' => false,
            'jira_used' => true,
            'jira_base_url' => 'https://mycompany.atlassian.net',
            'jira_email' => 'user@example.com',
            'jira_api_token' => 'my-api-token',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $account = Account::where('name', 'Jira Account')->first();

    expect($account)->not->toBeNull()
        ->and($account->jira_used)->toBeTrue()
        ->and($account->jira_base_url)->toBe('https://mycompany.atlassian.net')
        ->and($account->jira_email)->toBe('user@example.com')
        ->and($account->jira_api_token)->toBe('my-api-token');
});

test('admin can edit an account and leave jira token blank to preserve existing token', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withJira()->create([
        'user_id' => $user->id,
        'jira_api_token' => 'original-token',
    ]);

    $this->actingAs($user);

    Livewire::test(EditAccount::class, ['record' => $account->id])
        ->fillForm([
            'jira_used' => true,
            'jira_api_token' => '',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($account->fresh()->jira_api_token)->toBe('original-token');
});

test('admin can edit an account and supply a new jira token', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withJira()->create([
        'user_id' => $user->id,
        'jira_api_token' => 'old-token',
    ]);

    $this->actingAs($user);

    Livewire::test(EditAccount::class, ['record' => $account->id])
        ->fillForm([
            'jira_used' => true,
            'jira_api_token' => 'new-token',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($account->fresh()->jira_api_token)->toBe('new-token');
});
