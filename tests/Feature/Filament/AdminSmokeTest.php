<?php

use App\Filament\Resources\AccessTokenResource\Pages\CreateAccessToken;
use App\Filament\Resources\AccessTokenResource\Pages\ListAccessTokens;
use App\Filament\Resources\AccountResource\Pages\CreateAccount;
use App\Filament\Resources\AccountResource\Pages\EditAccount;
use App\Filament\Resources\AccountResource\Pages\ListAccounts;
use App\Models\Account;
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

test('accounts list page renders', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(ListAccounts::class)
        ->assertSuccessful();
});

test('accounts create page renders', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(CreateAccount::class)
        ->assertSuccessful();
});

test('accounts edit page renders', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id]);

    $this->actingAs($user);

    Livewire::test(EditAccount::class, ['record' => $account->id])
        ->assertSuccessful();
});
