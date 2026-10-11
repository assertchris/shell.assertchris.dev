<?php

use AC\LaravelTaggedCache\TaggedCache;
use App\Models\Account;
use App\Services\CalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('cache.default', 'array');
});

test('CalendarService::listEntries uses tagged cache and skips closure on second call', function () {
    $account = Account::factory()->create();

    $cached = ['items' => [['id' => 'evt1', 'title' => 'Meeting', 'start' => null, 'end' => null, 'location' => null]], 'nextPageToken' => null];

    TaggedCache::for(
        tags: ['calendar', "calendar:{$account->id}"],
        context: ['type' => 'entries', 'maxResults' => 50, 'pageToken' => null],
    )->put($cached, now()->addMinutes(5));

    $service = new CalendarService;

    $result = $service->listEntries($account);

    expect($result)->toBe($cached);
});

test('CalendarService account tag flush clears listEntries cache', function () {
    $account = Account::factory()->create();

    TaggedCache::for(
        tags: ['calendar', "calendar:{$account->id}"],
        context: ['type' => 'entries', 'maxResults' => 50, 'pageToken' => null],
    )->put(['items' => [], 'nextPageToken' => null], now()->addMinutes(5));

    expect(
        TaggedCache::for(
            tags: ['calendar', "calendar:{$account->id}"],
            context: ['type' => 'entries', 'maxResults' => 50, 'pageToken' => null],
        )->get()
    )->not->toBeNull();

    TaggedCache::for(tags: ["calendar:{$account->id}"])->flush();

    expect(
        TaggedCache::for(
            tags: ['calendar', "calendar:{$account->id}"],
            context: ['type' => 'entries', 'maxResults' => 50, 'pageToken' => null],
        )->get()
    )->toBeNull();
});

test('CalendarService::getEntry uses tagged cache and skips closure on second call', function () {
    $account = Account::factory()->create();

    $cached = [
        'id' => 'evt1',
        'title' => 'Team Standup',
        'description' => null,
        'start' => '2026-10-10T09:00:00+02:00',
        'end' => '2026-10-10T09:30:00+02:00',
        'location' => null,
        'attendees' => [],
        'html_link' => null,
    ];

    TaggedCache::for(
        tags: ['calendar', "calendar:{$account->id}"],
        context: ['type' => 'event', 'eventId' => 'evt1'],
    )->put($cached, now()->addMinutes(10));

    $service = new CalendarService;

    $result = $service->getEntry($account, 'evt1');

    expect($result)->toBe($cached);
});

test('CalendarService account tag flush clears getEntry cache', function () {
    $account = Account::factory()->create();

    TaggedCache::for(
        tags: ['calendar', "calendar:{$account->id}"],
        context: ['type' => 'event', 'eventId' => 'evt1'],
    )->put(['id' => 'evt1'], now()->addMinutes(10));

    TaggedCache::for(tags: ["calendar:{$account->id}"])->flush();

    expect(
        TaggedCache::for(
            tags: ['calendar', "calendar:{$account->id}"],
            context: ['type' => 'event', 'eventId' => 'evt1'],
        )->get()
    )->toBeNull();
});
