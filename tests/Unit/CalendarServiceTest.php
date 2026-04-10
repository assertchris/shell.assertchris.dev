<?php

use App\Models\Account;
use App\Services\CalendarService;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventAttendee;
use Google\Service\Calendar\EventDateTime;
use Google\Service\Calendar\Events;
use Google\Service\Calendar\Resource\Events as EventsResource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/**
 * Build a mock Google Client that reports the token as not expired.
 */
function mockGoogleClient(bool $tokenExpired = false): Client
{
    $client = Mockery::mock(Client::class);

    $client->shouldReceive('setAccessToken')->withAnyArgs();
    $client->shouldReceive('isAccessTokenExpired')->andReturn($tokenExpired);
    $client->shouldReceive('getAccessToken')->andReturn(['access_token' => 'token']);

    return $client;
}

/**
 * Build a mock EventDateTime.
 */
function mockEventDateTime(?string $dateTime = null, ?string $date = null): EventDateTime
{
    $dt = Mockery::mock(EventDateTime::class);
    $dt->shouldReceive('getDateTime')->andReturn($dateTime);
    $dt->shouldReceive('getDate')->andReturn($date);

    return $dt;
}

/**
 * Build a CalendarService that uses the provided mock client.
 */
function serviceWithClient(Client $client): CalendarService
{
    $service = Mockery::mock(CalendarService::class)->makePartial();
    $service->shouldAllowMockingProtectedMethods();
    $service->shouldReceive('buildClient')->andReturn($client);

    return $service;
}

test('connect returns a Calendar service with a valid token', function () {
    $client = mockGoogleClient(tokenExpired: false);
    $service = serviceWithClient($client);

    $account = Account::factory()->withGoogleCalendar()->create();

    $result = $service->connect($account);

    expect($result)->toBeInstanceOf(Calendar::class);
});

test('connect refreshes and persists the token when expired', function () {
    $newToken = [
        'access_token' => 'new-access-token',
        'expires_in' => 3600,
    ];

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('setAccessToken')->withAnyArgs();
    $client->shouldReceive('isAccessTokenExpired')->andReturn(true);
    $client->shouldReceive('fetchAccessTokenWithRefreshToken')->once()->andReturn($newToken);
    $client->shouldReceive('getAccessToken')->andReturn($newToken);

    $service = serviceWithClient($client);

    $account = Account::factory()->withGoogleCalendar()->create([
        'google_calendar_token_expires_at' => now()->subHour(),
    ]);

    $service->connect($account);

    $account->refresh();

    expect($account->google_calendar_access_token)->toBe('new-access-token');
    expect($account->google_calendar_token_expires_at)->not->toBeNull();
    expect($account->google_calendar_token_expires_at->isFuture())->toBeTrue();
});

test('listEntries maps items to the expected shape', function () {
    $start = mockEventDateTime('2026-04-10T09:00:00+00:00');
    $end = mockEventDateTime('2026-04-10T10:00:00+00:00');

    $event = Mockery::mock(Event::class);
    $event->shouldReceive('getId')->andReturn('event-1');
    $event->shouldReceive('getSummary')->andReturn('Team Meeting');
    $event->shouldReceive('getStart')->andReturn($start);
    $event->shouldReceive('getEnd')->andReturn($end);
    $event->shouldReceive('getLocation')->andReturn('Conference Room A');

    $eventsModel = Mockery::mock(Events::class);
    $eventsModel->shouldReceive('getItems')->andReturn([$event]);
    $eventsModel->shouldReceive('getNextPageToken')->andReturn(null);

    $eventsResource = Mockery::mock(EventsResource::class);
    $eventsResource->shouldReceive('listEvents')->once()->andReturn($eventsModel);

    $calendarService = Mockery::mock(Calendar::class);
    $calendarService->events = $eventsResource;

    $service = Mockery::mock(CalendarService::class)->makePartial();
    $service->shouldAllowMockingProtectedMethods();
    $service->shouldReceive('connect')->andReturn($calendarService);

    $account = Account::factory()->withGoogleCalendar()->make();

    $result = $service->listEntries($account);

    expect($result)->toHaveKeys(['items', 'nextPageToken']);
    expect($result['nextPageToken'])->toBeNull();
    expect($result['items'])->toHaveCount(1);
    expect($result['items'][0])->toBe([
        'id' => 'event-1',
        'title' => 'Team Meeting',
        'start' => '2026-04-10T09:00:00+00:00',
        'end' => '2026-04-10T10:00:00+00:00',
        'location' => 'Conference Room A',
    ]);
});

test('listEntries falls back to date for all-day events', function () {
    $start = mockEventDateTime(null, '2026-04-10');
    $end = mockEventDateTime(null, '2026-04-11');

    $event = Mockery::mock(Event::class);
    $event->shouldReceive('getId')->andReturn('event-2');
    $event->shouldReceive('getSummary')->andReturn('All Day Event');
    $event->shouldReceive('getStart')->andReturn($start);
    $event->shouldReceive('getEnd')->andReturn($end);
    $event->shouldReceive('getLocation')->andReturn(null);

    $eventsModel = Mockery::mock(Events::class);
    $eventsModel->shouldReceive('getItems')->andReturn([$event]);
    $eventsModel->shouldReceive('getNextPageToken')->andReturn('next-page-token');

    $eventsResource = Mockery::mock(EventsResource::class);
    $eventsResource->shouldReceive('listEvents')->andReturn($eventsModel);

    $calendarService = Mockery::mock(Calendar::class);
    $calendarService->events = $eventsResource;

    $service = Mockery::mock(CalendarService::class)->makePartial();
    $service->shouldAllowMockingProtectedMethods();
    $service->shouldReceive('connect')->andReturn($calendarService);

    $account = Account::factory()->withGoogleCalendar()->make();

    $result = $service->listEntries($account);

    expect($result['items'][0]['start'])->toBe('2026-04-10');
    expect($result['items'][0]['end'])->toBe('2026-04-11');
    expect($result['nextPageToken'])->toBe('next-page-token');
});

test('getEntry maps a single event to the expected shape', function () {
    $start = mockEventDateTime('2026-04-10T09:00:00+00:00');
    $end = mockEventDateTime('2026-04-10T10:00:00+00:00');

    $attendee = Mockery::mock(EventAttendee::class);
    $attendee->shouldReceive('getEmail')->andReturn('alice@example.com');
    $attendee->shouldReceive('getDisplayName')->andReturn('Alice');
    $attendee->shouldReceive('getResponseStatus')->andReturn('accepted');

    $event = Mockery::mock(Event::class);
    $event->shouldReceive('getId')->andReturn('event-3');
    $event->shouldReceive('getSummary')->andReturn('Project Review');
    $event->shouldReceive('getDescription')->andReturn('Quarterly review');
    $event->shouldReceive('getStart')->andReturn($start);
    $event->shouldReceive('getEnd')->andReturn($end);
    $event->shouldReceive('getLocation')->andReturn('Zoom');
    $event->shouldReceive('getAttendees')->andReturn([$attendee]);
    $event->shouldReceive('getHtmlLink')->andReturn('https://calendar.google.com/event?eid=event-3');

    $eventsResource = Mockery::mock(EventsResource::class);
    $eventsResource->shouldReceive('get')->once()->with('primary', 'event-3')->andReturn($event);

    $calendarService = Mockery::mock(Calendar::class);
    $calendarService->events = $eventsResource;

    $service = Mockery::mock(CalendarService::class)->makePartial();
    $service->shouldAllowMockingProtectedMethods();
    $service->shouldReceive('connect')->andReturn($calendarService);

    $account = Account::factory()->withGoogleCalendar()->make();

    $result = $service->getEntry($account, 'event-3');

    expect($result)->toBe([
        'id' => 'event-3',
        'title' => 'Project Review',
        'description' => 'Quarterly review',
        'start' => '2026-04-10T09:00:00+00:00',
        'end' => '2026-04-10T10:00:00+00:00',
        'location' => 'Zoom',
        'attendees' => [
            [
                'email' => 'alice@example.com',
                'name' => 'Alice',
                'responseStatus' => 'accepted',
            ],
        ],
        'html_link' => 'https://calendar.google.com/event?eid=event-3',
    ]);
});

test('getEntry handles event with no attendees', function () {
    $event = Mockery::mock(Event::class);
    $event->shouldReceive('getId')->andReturn('event-4');
    $event->shouldReceive('getSummary')->andReturn('Solo Task');
    $event->shouldReceive('getDescription')->andReturn(null);
    $event->shouldReceive('getStart')->andReturn(mockEventDateTime('2026-04-10T14:00:00+00:00'));
    $event->shouldReceive('getEnd')->andReturn(mockEventDateTime('2026-04-10T15:00:00+00:00'));
    $event->shouldReceive('getLocation')->andReturn(null);
    $event->shouldReceive('getAttendees')->andReturn(null);
    $event->shouldReceive('getHtmlLink')->andReturn(null);

    $eventsResource = Mockery::mock(EventsResource::class);
    $eventsResource->shouldReceive('get')->andReturn($event);

    $calendarService = Mockery::mock(Calendar::class);
    $calendarService->events = $eventsResource;

    $service = Mockery::mock(CalendarService::class)->makePartial();
    $service->shouldAllowMockingProtectedMethods();
    $service->shouldReceive('connect')->andReturn($calendarService);

    $account = Account::factory()->withGoogleCalendar()->make();

    $result = $service->getEntry($account, 'event-4');

    expect($result['attendees'])->toBe([]);
    expect($result['description'])->toBeNull();
    expect($result['html_link'])->toBeNull();
});
