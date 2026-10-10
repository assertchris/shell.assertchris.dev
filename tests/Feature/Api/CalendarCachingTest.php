<?php

use App\Models\Account;
use App\Models\User;
use App\Services\CalendarService;
use App\Support\CacheKey;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

test('listEntries is served from cache on second call', function () {
    $account = Account::factory()->withGoogleCalendar()->for(User::factory()->create())->create();

    $cached = ['items' => [['id' => 'evt1', 'title' => 'Standup', 'start' => null, 'end' => null, 'location' => null]], 'nextPageToken' => null];
    $cacheKey = (string) CacheKey::hashed(['maxResults' => 50, 'pageToken' => null], 'calendar', $account->id, 'entries');
    Cache::put($cacheKey, $cached, now()->addMinutes(5));

    $service = $this->partialMock(CalendarService::class, function ($mock) {
        $mock->shouldNotReceive('connect');
    });

    $result = $service->listEntries($account);

    expect($result)->toBe($cached);
    expect(Cache::has($cacheKey))->toBeTrue();
});

test('createEntry busts the listEntries cache key', function () {
    $user = User::factory()->create();
    $account = Account::factory()->withGoogleCalendar()->for($user)->create();
    $token = $user->createToken('test');

    $cacheKey = (string) CacheKey::hashed([], 'calendar', $account->id, 'entries');
    Cache::put($cacheKey, ['items' => [], 'nextPageToken' => null], now()->addMinutes(5));

    expect(Cache::has($cacheKey))->toBeTrue();

    $this->mock(CalendarService::class, function ($mock) {
        $mock->shouldReceive('createEntry')
            ->once()
            ->andReturn([
                'id' => 'evt-new',
                'title' => 'New Event',
                'description' => null,
                'start' => '2026-10-10T09:00:00+02:00',
                'end' => '2026-10-10T10:00:00+02:00',
                'location' => null,
                'attendees' => [],
                'html_link' => null,
            ]);
    });

    $this->postJson("/api/{$account->id}/calendar", [
        'summary' => 'New Event',
        'start' => ['dateTime' => '2026-10-10T09:00:00+02:00', 'timeZone' => 'Africa/Johannesburg'],
        'end' => ['dateTime' => '2026-10-10T10:00:00+02:00', 'timeZone' => 'Africa/Johannesburg'],
    ], [
        'Authorization' => 'Bearer '.$token->plainTextToken,
    ])->assertCreated();

    expect(Cache::missing($cacheKey))->toBeTrue();
});
