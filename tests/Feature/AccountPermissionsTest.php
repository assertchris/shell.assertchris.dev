<?php

use App\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// --- Boolean casts ---

it('casts google_calendar_read to boolean', function () {
    $account = Account::factory()->create(['google_calendar_read' => 1]);

    expect($account->fresh()->google_calendar_read)->toBeTrue()->toBeBool();
});

it('casts google_calendar_write to boolean', function () {
    $account = Account::factory()->create(['google_calendar_write' => 0]);

    expect($account->fresh()->google_calendar_write)->toBeFalse()->toBeBool();
});

it('casts mail_read to boolean', function () {
    $account = Account::factory()->create(['mail_read' => 1]);

    expect($account->fresh()->mail_read)->toBeTrue()->toBeBool();
});

it('casts mail_write to boolean', function () {
    $account = Account::factory()->create(['mail_write' => 0]);

    expect($account->fresh()->mail_write)->toBeFalse()->toBeBool();
});

it('casts slack_read to boolean', function () {
    $account = Account::factory()->create(['slack_read' => 1]);

    expect($account->fresh()->slack_read)->toBeTrue()->toBeBool();
});

it('casts slack_write to boolean', function () {
    $account = Account::factory()->create(['slack_write' => 0]);

    expect($account->fresh()->slack_write)->toBeFalse()->toBeBool();
});

// --- Mass assignability ---

it('allows google_calendar_read to be mass assigned', function () {
    $account = Account::factory()->make();
    $account->fill(['google_calendar_read' => false]);

    expect($account->google_calendar_read)->toBeFalse();
});

it('allows google_calendar_write to be mass assigned', function () {
    $account = Account::factory()->make();
    $account->fill(['google_calendar_write' => false]);

    expect($account->google_calendar_write)->toBeFalse();
});

it('allows mail_read to be mass assigned', function () {
    $account = Account::factory()->make();
    $account->fill(['mail_read' => false]);

    expect($account->mail_read)->toBeFalse();
});

it('allows mail_write to be mass assigned', function () {
    $account = Account::factory()->make();
    $account->fill(['mail_write' => false]);

    expect($account->mail_write)->toBeFalse();
});

it('allows slack_read to be mass assigned', function () {
    $account = Account::factory()->make();
    $account->fill(['slack_read' => false]);

    expect($account->slack_read)->toBeFalse();
});

it('allows slack_write to be mass assigned', function () {
    $account = Account::factory()->make();
    $account->fill(['slack_write' => false]);

    expect($account->slack_write)->toBeFalse();
});

// --- Default column values ---

it('defaults all fine-grained permission columns to true on a freshly created account', function () {
    $account = Account::factory()->create();

    expect($account->fresh())
        ->google_calendar_read->toBeTrue()
        ->google_calendar_write->toBeTrue()
        ->mail_read->toBeTrue()
        ->mail_write->toBeTrue()
        ->slack_read->toBeTrue()
        ->slack_write->toBeTrue();
});

// --- Factory states ---

it('withGoogleCalendar state sets google_calendar_read and google_calendar_write to true', function () {
    $account = Account::factory()->withGoogleCalendar()->create();

    expect($account->google_calendar_read)->toBeTrue()
        ->and($account->google_calendar_write)->toBeTrue();
});

it('withCalendarReadOnly state sets google_calendar_read to true and google_calendar_write to false', function () {
    $account = Account::factory()->withGoogleCalendar()->withCalendarReadOnly()->create();

    expect($account->google_calendar_read)->toBeTrue()
        ->and($account->google_calendar_write)->toBeFalse();
});

it('withSlack state sets slack_read and slack_write to true', function () {
    $account = Account::factory()->withSlack()->create();

    expect($account->slack_read)->toBeTrue()
        ->and($account->slack_write)->toBeTrue();
});

it('withSlackReadOnly state sets slack_read to true and slack_write to false', function () {
    $account = Account::factory()->withSlack()->withSlackReadOnly()->create();

    expect($account->slack_read)->toBeTrue()
        ->and($account->slack_write)->toBeFalse();
});

it('mailOnly state sets mail_read and mail_write to true', function () {
    $account = Account::factory()->mailOnly()->create();

    expect($account->mail_read)->toBeTrue()
        ->and($account->mail_write)->toBeTrue();
});
