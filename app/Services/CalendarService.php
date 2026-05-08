<?php

namespace App\Services;

use App\Models\Account;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;

class CalendarService
{
    protected function buildClient(): Client
    {
        $client = new Client;
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));

        return $client;
    }

    public function connect(Account $account): Calendar
    {
        $client = $this->buildClient();

        $client->setAccessToken([
            'access_token' => $account->google_calendar_access_token,
            'refresh_token' => $account->google_calendar_refresh_token,
            'expires_in' => $account->google_calendar_token_expires_at
                ? $account->google_calendar_token_expires_at->diffInSeconds(now(), absolute: false)
                : 0,
            'created' => $account->google_calendar_token_expires_at
                ? $account->google_calendar_token_expires_at->subHour()->timestamp
                : 0,
        ]);

        if ($client->isAccessTokenExpired()) {
            $newToken = $client->fetchAccessTokenWithRefreshToken($account->google_calendar_refresh_token);

            $account->update([
                'google_calendar_access_token' => $newToken['access_token'],
                'google_calendar_token_expires_at' => now()->addSeconds($newToken['expires_in'] ?? 3600),
            ]);

            $client->setAccessToken($newToken);
        }

        return new Calendar($client);
    }

    /**
     * @return array{items: array<int, array{id: string, title: string, start: string|null, end: string|null, location: string|null}>, nextPageToken: string|null}
     */
    public function listEntries(Account $account, int $maxResults = 50, ?string $pageToken = null): array
    {
        $calendar = $this->connect($account);

        $params = [
            'maxResults' => $maxResults,
            'singleEvents' => true,
            'orderBy' => 'startTime',
            'timeMin' => now()->toRfc3339String(),
        ];

        if ($pageToken !== null) {
            $params['pageToken'] = $pageToken;
        }

        $events = $calendar->events->listEvents('primary', $params);

        $items = array_map(
            fn ($event) => [
                'id' => $event->getId(),
                'title' => $event->getSummary(),
                'start' => $event->getStart()?->getDateTime() ?? $event->getStart()?->getDate(),
                'end' => $event->getEnd()?->getDateTime() ?? $event->getEnd()?->getDate(),
                'location' => $event->getLocation(),
            ],
            $events->getItems() ?? []
        );

        return [
            'items' => $items,
            'nextPageToken' => $events->getNextPageToken(),
        ];
    }

    /**
     * @param  array{summary: string, description?: string|null, location?: string|null, start: array{dateTime: string, timeZone: string}, end: array{dateTime: string, timeZone: string}}  $data
     * @return array{id: string, title: string, description: string|null, start: string|null, end: string|null, location: string|null, attendees: array<int, array{email: string, name: string|null, responseStatus: string|null}>, html_link: string|null}
     */
    public function createEntry(Account $account, array $data): array
    {
        $calendar = $this->connect($account);

        $event = new Event;
        $event->setSummary($data['summary']);

        if (isset($data['description'])) {
            $event->setDescription($data['description']);
        }

        if (isset($data['location'])) {
            $event->setLocation($data['location']);
        }

        $start = new EventDateTime;
        $start->setDateTime($data['start']['dateTime']);
        $start->setTimeZone($data['start']['timeZone']);
        $event->setStart($start);

        $end = new EventDateTime;
        $end->setDateTime($data['end']['dateTime']);
        $end->setTimeZone($data['end']['timeZone']);
        $event->setEnd($end);

        $created = $calendar->events->insert('primary', $event);

        $attendees = array_map(
            fn ($attendee) => [
                'email' => $attendee->getEmail(),
                'name' => $attendee->getDisplayName(),
                'responseStatus' => $attendee->getResponseStatus(),
            ],
            $created->getAttendees() ?? []
        );

        return [
            'id' => $created->getId(),
            'title' => $created->getSummary(),
            'description' => $created->getDescription(),
            'start' => $created->getStart()?->getDateTime() ?? $created->getStart()?->getDate(),
            'end' => $created->getEnd()?->getDateTime() ?? $created->getEnd()?->getDate(),
            'location' => $created->getLocation(),
            'attendees' => $attendees,
            'html_link' => $created->getHtmlLink(),
        ];
    }

    /**
     * @return array{id: string, title: string, description: string|null, start: string|null, end: string|null, location: string|null, attendees: array<int, array{email: string, name: string|null, responseStatus: string|null}>, html_link: string|null}
     */
    public function getEntry(Account $account, string $eventId): array
    {
        $calendar = $this->connect($account);

        $event = $calendar->events->get('primary', $eventId);

        $attendees = array_map(
            fn ($attendee) => [
                'email' => $attendee->getEmail(),
                'name' => $attendee->getDisplayName(),
                'responseStatus' => $attendee->getResponseStatus(),
            ],
            $event->getAttendees() ?? []
        );

        return [
            'id' => $event->getId(),
            'title' => $event->getSummary(),
            'description' => $event->getDescription(),
            'start' => $event->getStart()?->getDateTime() ?? $event->getStart()?->getDate(),
            'end' => $event->getEnd()?->getDateTime() ?? $event->getEnd()?->getDate(),
            'location' => $event->getLocation(),
            'attendees' => $attendees,
            'html_link' => $event->getHtmlLink(),
        ];
    }
}
