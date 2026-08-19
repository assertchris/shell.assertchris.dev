<?php

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('account can be created via factory', function () {
    $account = Account::factory()->create();

    expect($account)->toBeInstanceOf(Account::class)
        ->and($account->exists)->toBeTrue();
});

test('account mail_password is encrypted at rest', function () {
    $account = Account::factory()->create(['mail_password' => 'secret-password']);

    $raw = DB::table('accounts')->where('id', $account->id)->value('mail_password');

    expect($raw)->not->toBe('secret-password')
        ->and($account->fresh()->mail_password)->toBe('secret-password');
});

test('account google_calendar_access_token is encrypted at rest', function () {
    $account = Account::factory()->withGoogleCalendar()->create();

    $raw = DB::table('accounts')->where('id', $account->id)->value('google_calendar_access_token');

    expect($raw)->not->toBe($account->google_calendar_access_token)
        ->and($account->fresh()->google_calendar_access_token)->not->toBeNull();
});

test('account is_active is cast to boolean', function () {
    $account = Account::factory()->create(['is_active' => 1]);

    expect($account->fresh()->is_active)->toBeTrue()->toBeBool();
});

test('account mail_used is cast to boolean', function () {
    $account = Account::factory()->create(['mail_used' => 1]);

    expect($account->fresh()->mail_used)->toBeTrue()->toBeBool();
});

test('account google_calendar_used is cast to boolean', function () {
    $account = Account::factory()->create(['google_calendar_used' => 0]);

    expect($account->fresh()->google_calendar_used)->toBeFalse()->toBeBool();
});

test('account belongs to a user', function () {
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_id' => $user->id]);

    expect($account->user->id)->toBe($user->id);
});

test('user has many accounts', function () {
    $user = User::factory()->create();
    Account::factory()->count(3)->create(['user_id' => $user->id]);

    expect($user->accounts)->toHaveCount(3);
});

test('isGoogleCalendarConnected returns true when connected', function () {
    $account = Account::factory()->withGoogleCalendar()->create();

    expect($account->isGoogleCalendarConnected())->toBeTrue();
});

test('isGoogleCalendarConnected returns false when token is null', function () {
    $account = Account::factory()->mailOnly()->create();

    expect($account->isGoogleCalendarConnected())->toBeFalse();
});

test('isGoogleCalendarConnected returns false when google_calendar_used is false', function () {
    $account = Account::factory()->create([
        'google_calendar_used' => false,
        'google_calendar_access_token' => 'some-token',
    ]);

    expect($account->isGoogleCalendarConnected())->toBeFalse();
});

test('inactive factory state sets is_active to false', function () {
    $account = Account::factory()->inactive()->create();

    expect($account->is_active)->toBeFalse();
});

test('withGoogleCalendar factory state sets google calendar fields', function () {
    $account = Account::factory()->withGoogleCalendar()->create();

    expect($account->google_calendar_used)->toBeTrue()
        ->and($account->google_calendar_access_token)->not->toBeNull()
        ->and($account->google_calendar_refresh_token)->not->toBeNull()
        ->and($account->google_calendar_token_expires_at)->not->toBeNull();
});

test('mailOnly factory state clears google calendar fields', function () {
    $account = Account::factory()->mailOnly()->create();

    expect($account->google_calendar_used)->toBeFalse()
        ->and($account->google_calendar_access_token)->toBeNull()
        ->and($account->google_calendar_refresh_token)->toBeNull()
        ->and($account->google_calendar_token_expires_at)->toBeNull();
});

test('account github_pat is encrypted at rest', function () {
    $plaintext = fake()->sha256();
    $account = Account::factory()->create(['github_used' => true, 'github_pat' => $plaintext]);

    $raw = DB::table('accounts')->where('id', $account->id)->value('github_pat');

    expect($raw)->not->toBe($plaintext)
        ->and($account->fresh()->github_pat)->toBe($plaintext);
});

test('account github_used is cast to boolean', function () {
    $account = Account::factory()->create(['github_used' => 1]);

    expect($account->fresh()->github_used)->toBeTrue()->toBeBool();
});

test('isGitHubConnected returns true when github_used is true and pat is set', function () {
    $account = Account::factory()->withGitHub()->create();

    expect($account->isGitHubConnected())->toBeTrue();
});

test('isGitHubConnected returns false when github_used is false even if pat is set', function () {
    $account = Account::factory()->create([
        'github_used' => false,
        'github_pat' => fake()->sha256(),
    ]);

    expect($account->isGitHubConnected())->toBeFalse();
});

test('isGitHubConnected returns false when github_pat is null', function () {
    $account = Account::factory()->create([
        'github_used' => true,
        'github_pat' => null,
    ]);

    expect($account->isGitHubConnected())->toBeFalse();
});

test('toGitHubConfig returns decrypted pat under pat key', function () {
    $plaintext = fake()->sha256();
    $account = Account::factory()->create(['github_used' => true, 'github_pat' => $plaintext]);

    expect($account->toGitHubConfig())->toBe(['pat' => $plaintext]);
});

test('withGitHub factory state produces a connectable account', function () {
    $account = Account::factory()->withGitHub()->create();

    expect($account->github_used)->toBeTrue()
        ->and($account->github_pat)->not->toBeNull()
        ->and($account->isGitHubConnected())->toBeTrue();
});
