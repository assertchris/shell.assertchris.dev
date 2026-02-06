<?php

use App\Filament\Resources\AccessTokenResource\Pages\CreateAccessToken;
use App\Filament\Resources\AccessTokenResource\Pages\ListAccessTokens;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('admin can view token list page', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(ListAccessTokens::class)
        ->assertSuccessful();
});

test('admin can see their tokens in the list', function () {
    $user = User::factory()->create();
    $user->createToken('my-token');

    $this->actingAs($user);

    Livewire::test(ListAccessTokens::class)
        ->assertSee('my-token');
});

test('admin can render create token page', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(CreateAccessToken::class)
        ->assertSuccessful();
});

test('admin can create a token', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(CreateAccessToken::class)
        ->fillForm([
            'name' => 'new-token',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(PersonalAccessToken::where('name', 'new-token')->exists())->toBeTrue();
});

test('admin can delete a token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('deletable-token');
    $tokenId = $token->accessToken->id;

    $this->actingAs($user);

    Livewire::test(ListAccessTokens::class)
        ->callTableAction('delete', $tokenId);

    expect(PersonalAccessToken::find($tokenId))->toBeNull();
});
