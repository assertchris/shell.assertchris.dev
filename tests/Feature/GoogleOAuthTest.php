<?php

use App\Filament\Resources\AccountResource;
use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Factory as SocialiteFactory;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

test('redirect route returns 302 to google', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id]);

    $googleProvider = Mockery::mock(GoogleProvider::class);
    $googleProvider->shouldReceive('scopes')->once()->andReturnSelf();
    $googleProvider->shouldReceive('with')->once()->andReturnSelf();
    $googleProvider->shouldReceive('redirect')->once()->andReturn(redirect('https://accounts.google.com/o/oauth2/auth?fake=1'));

    $socialite = Mockery::mock(SocialiteFactory::class);
    $socialite->shouldReceive('driver')->with('google')->andReturn($googleProvider);

    $this->app->instance(SocialiteFactory::class, $socialite);

    $this->actingAs($user)
        ->get(route('google.redirect', $account))
        ->assertRedirect();
});

test('redirect route stores account id in session', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id]);

    $googleProvider = Mockery::mock(GoogleProvider::class);
    $googleProvider->shouldReceive('scopes')->once()->andReturnSelf();
    $googleProvider->shouldReceive('with')->once()->andReturnSelf();
    $googleProvider->shouldReceive('redirect')->once()->andReturn(redirect('https://accounts.google.com/'));

    $socialite = Mockery::mock(SocialiteFactory::class);
    $socialite->shouldReceive('driver')->with('google')->andReturn($googleProvider);

    $this->app->instance(SocialiteFactory::class, $socialite);

    $this->actingAs($user)
        ->get(route('google.redirect', $account));

    $this->assertEquals($account->id, session('google_oauth_account_id'));
});

test('redirect route returns 403 for wrong user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $otherUser->id]);

    $this->actingAs($user)
        ->get(route('google.redirect', $account))
        ->assertForbidden();
});

test('callback stores tokens and redirects to filament edit page', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id]);

    session(['google_oauth_account_id' => $account->id]);

    $socialiteUser = Mockery::mock(SocialiteUser::class);
    $socialiteUser->token = 'fake-access-token';
    $socialiteUser->refreshToken = 'fake-refresh-token';
    $socialiteUser->expiresIn = 3600;

    $googleProvider = Mockery::mock(GoogleProvider::class);
    $googleProvider->shouldReceive('user')->once()->andReturn($socialiteUser);

    $socialite = Mockery::mock(SocialiteFactory::class);
    $socialite->shouldReceive('driver')->with('google')->andReturn($googleProvider);

    $this->app->instance(SocialiteFactory::class, $socialite);

    $this->actingAs($user)
        ->get(route('google.callback'))
        ->assertRedirect(AccountResource::getUrl('edit', ['record' => $account]));

    $account->refresh();

    expect($account->google_calendar_access_token)->toBe('fake-access-token')
        ->and($account->google_calendar_refresh_token)->toBe('fake-refresh-token')
        ->and($account->google_calendar_used)->toBeTrue()
        ->and($account->google_calendar_token_expires_at)->not->toBeNull();
});

test('callback returns 403 for wrong user', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $otherUser->id]);

    session(['google_oauth_account_id' => $account->id]);

    $this->actingAs($user)
        ->get(route('google.callback'))
        ->assertForbidden();
});

test('disconnect action clears tokens and disables google calendar', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withGoogleCalendar()->create(['user_id' => $user->id]);

    expect($account->isGoogleCalendarConnected())->toBeTrue();

    $account->update([
        'google_calendar_access_token' => null,
        'google_calendar_refresh_token' => null,
        'google_calendar_token_expires_at' => null,
        'google_calendar_used' => false,
    ]);

    $account->refresh();

    expect($account->google_calendar_access_token)->toBeNull()
        ->and($account->google_calendar_refresh_token)->toBeNull()
        ->and($account->google_calendar_token_expires_at)->toBeNull()
        ->and($account->google_calendar_used)->toBeFalse()
        ->and($account->isGoogleCalendarConnected())->toBeFalse();
});
