<?php

use App\Models\Account;
use App\Models\User;
use App\Services\CalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('list calendar entries requires authentication', function () {
    $account = Account::factory()->withGoogleCalendar()->create();

    $this->getJson("/api/{$account->id}/calendar")
        ->assertUnauthorized();
});

test('list calendar entries returns 404 for other user account', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $account = Account::factory()->withGoogleCalendar()->for($otherUser)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/calendar", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

test('list calendar entries returns 404 when not connected', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['google_calendar_used' => false]);
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/calendar", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

test('list calendar entries returns 200 with correct shape', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withGoogleCalendar()->for($user)->create();
    $token = $user->createToken('test');

    $entries = [
        ['id' => 'evt1', 'title' => 'Team Standup', 'start' => '2026-04-10T09:00:00Z', 'end' => '2026-04-10T09:30:00Z', 'location' => 'Zoom'],
        ['id' => 'evt2', 'title' => 'Lunch', 'start' => '2026-04-10T12:00:00Z', 'end' => '2026-04-10T13:00:00Z', 'location' => null],
    ];

    $this->mock(CalendarService::class, function ($mock) use ($entries) {
        $mock->shouldReceive('listEntries')
            ->once()
            ->andReturn(['items' => $entries, 'nextPageToken' => null]);
    });

    $this->getJson("/api/{$account->id}/calendar", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertSuccessful()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', 'evt1')
        ->assertJsonPath('data.0.title', 'Team Standup')
        ->assertJsonPath('data.0.start', '2026-04-10T09:00:00Z')
        ->assertJsonPath('data.0.end', '2026-04-10T09:30:00Z')
        ->assertJsonPath('data.0.location', 'Zoom')
        ->assertJsonPath('data.1.id', 'evt2');
});

test('list calendar entries returns 200 with meta', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withGoogleCalendar()->for($user)->create();
    $token = $user->createToken('test');

    $this->mock(CalendarService::class, function ($mock) {
        $mock->shouldReceive('listEntries')
            ->once()
            ->andReturn(['items' => [], 'nextPageToken' => 'token123']);
    });

    $this->getJson("/api/{$account->id}/calendar?page=2&per_page=10", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertSuccessful()
        ->assertJsonPath('meta.page', 2)
        ->assertJsonPath('meta.per_page', 10)
        ->assertJsonPath('meta.next_page_token', 'token123');
});

test('show calendar entry requires authentication', function () {
    $account = Account::factory()->withGoogleCalendar()->create();

    $this->getJson("/api/{$account->id}/calendar/evt1")
        ->assertUnauthorized();
});

test('show calendar entry returns 404 for other user account', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $account = Account::factory()->withGoogleCalendar()->for($otherUser)->create();
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/calendar/evt1", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

test('show calendar entry returns 404 when not connected', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['google_calendar_used' => false]);
    $token = $user->createToken('test');

    $this->getJson("/api/{$account->id}/calendar/evt1", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

test('show calendar entry returns 200 with full shape', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withGoogleCalendar()->for($user)->create();
    $token = $user->createToken('test');

    $entry = [
        'id' => 'evt1',
        'title' => 'Team Standup',
        'description' => 'Daily sync',
        'start' => '2026-04-10T09:00:00Z',
        'end' => '2026-04-10T09:30:00Z',
        'location' => 'Zoom',
        'attendees' => [
            ['email' => 'alice@example.com', 'name' => 'Alice', 'responseStatus' => 'accepted'],
        ],
        'html_link' => 'https://calendar.google.com/event?eid=evt1',
    ];

    $this->mock(CalendarService::class, function ($mock) use ($entry) {
        $mock->shouldReceive('getEntry')
            ->once()
            ->with(\Mockery::any(), 'evt1')
            ->andReturn($entry);
    });

    $this->getJson("/api/{$account->id}/calendar/evt1", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.id', 'evt1')
        ->assertJsonPath('data.title', 'Team Standup')
        ->assertJsonPath('data.description', 'Daily sync')
        ->assertJsonPath('data.start', '2026-04-10T09:00:00Z')
        ->assertJsonPath('data.end', '2026-04-10T09:30:00Z')
        ->assertJsonPath('data.location', 'Zoom')
        ->assertJsonPath('data.attendees.0.email', 'alice@example.com')
        ->assertJsonPath('data.attendees.0.name', 'Alice')
        ->assertJsonPath('data.attendees.0.responseStatus', 'accepted')
        ->assertJsonPath('data.html_link', 'https://calendar.google.com/event?eid=evt1');
});

test('list calendar entries returns 503 on service error', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withGoogleCalendar()->for($user)->create();
    $token = $user->createToken('test');

    $this->mock(CalendarService::class, function ($mock) {
        $mock->shouldReceive('listEntries')
            ->once()
            ->andThrow(new \RuntimeException('Google API error'));
    });

    $this->getJson("/api/{$account->id}/calendar", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertServiceUnavailable();
});

test('show calendar entry returns 503 on service error', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withGoogleCalendar()->for($user)->create();
    $token = $user->createToken('test');

    $this->mock(CalendarService::class, function ($mock) {
        $mock->shouldReceive('getEntry')
            ->once()
            ->andThrow(new \RuntimeException('Google API error'));
    });

    $this->getJson("/api/{$account->id}/calendar/evt1", [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertServiceUnavailable();
});

test('create calendar entry requires authentication', function () {
    $account = Account::factory()->withGoogleCalendar()->create();

    $this->postJson("/api/{$account->id}/calendar", [])
        ->assertUnauthorized();
});

test('create calendar entry returns 404 when not connected', function () {
    $user = User::factory()->create();
    $account = Account::factory()->for($user)->create(['google_calendar_used' => false]);
    $token = $user->createToken('test');

    $this->postJson("/api/{$account->id}/calendar", [], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertNotFound();
});

test('create calendar entry returns 403 when write permission is disabled', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withGoogleCalendar()->withCalendarReadOnly()->for($user)->create();
    $token = $user->createToken('test');

    $this->postJson("/api/{$account->id}/calendar", [
        'summary' => 'New Meeting',
        'start' => ['dateTime' => '2026-04-10T09:00:00+00:00', 'timeZone' => 'UTC'],
        'end' => ['dateTime' => '2026-04-10T10:00:00+00:00', 'timeZone' => 'UTC'],
    ], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertForbidden();
});

test('create calendar entry returns 422 for missing summary', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withGoogleCalendar()->for($user)->create();
    $token = $user->createToken('test');

    $this->postJson("/api/{$account->id}/calendar", [
        'start' => ['dateTime' => '2026-04-10T09:00:00+00:00', 'timeZone' => 'UTC'],
        'end' => ['dateTime' => '2026-04-10T10:00:00+00:00', 'timeZone' => 'UTC'],
    ], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['summary']);
});

test('create calendar entry returns 422 for bad dateTime format', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withGoogleCalendar()->for($user)->create();
    $token = $user->createToken('test');

    $this->postJson("/api/{$account->id}/calendar", [
        'summary' => 'New Meeting',
        'start' => ['dateTime' => 'not-a-date', 'timeZone' => 'UTC'],
        'end' => ['dateTime' => '2026-04-10T10:00:00+00:00', 'timeZone' => 'UTC'],
    ], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['start.dateTime']);
});

test('create calendar entry returns 201 with correct shape', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withGoogleCalendar()->for($user)->create();
    $token = $user->createToken('test');

    $created = [
        'id' => 'new-evt-1',
        'title' => 'New Meeting',
        'description' => 'A new event',
        'start' => '2026-04-10T09:00:00+00:00',
        'end' => '2026-04-10T10:00:00+00:00',
        'location' => 'Conference Room A',
        'attendees' => [],
        'html_link' => 'https://calendar.google.com/event?eid=new-evt-1',
    ];

    $this->mock(CalendarService::class, function ($mock) use ($created) {
        $mock->shouldReceive('createEntry')
            ->once()
            ->andReturn($created);
    });

    $this->postJson("/api/{$account->id}/calendar", [
        'summary' => 'New Meeting',
        'description' => 'A new event',
        'location' => 'Conference Room A',
        'start' => ['dateTime' => '2026-04-10T09:00:00+00:00', 'timeZone' => 'UTC'],
        'end' => ['dateTime' => '2026-04-10T10:00:00+00:00', 'timeZone' => 'UTC'],
    ], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertCreated()
        ->assertJsonPath('data.id', 'new-evt-1')
        ->assertJsonPath('data.title', 'New Meeting')
        ->assertJsonPath('data.description', 'A new event')
        ->assertJsonPath('data.location', 'Conference Room A')
        ->assertJsonPath('data.start', '2026-04-10T09:00:00+00:00')
        ->assertJsonPath('data.end', '2026-04-10T10:00:00+00:00');
});

test('create calendar entry returns 503 on service error', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withGoogleCalendar()->for($user)->create();
    $token = $user->createToken('test');

    $this->mock(CalendarService::class, function ($mock) {
        $mock->shouldReceive('createEntry')
            ->once()
            ->andThrow(new \RuntimeException('Google API error'));
    });

    $this->postJson("/api/{$account->id}/calendar", [
        'summary' => 'New Meeting',
        'start' => ['dateTime' => '2026-04-10T09:00:00+00:00', 'timeZone' => 'UTC'],
        'end' => ['dateTime' => '2026-04-10T10:00:00+00:00', 'timeZone' => 'UTC'],
    ], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])
        ->assertServiceUnavailable();
});
