<?php

use App\Filament\Resources\AccessTokenResource\Pages\CreateAccessToken;
use App\Filament\Resources\AccessTokenResource\Pages\ListAccessTokens;
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
