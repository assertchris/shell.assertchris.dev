<?php

namespace App\Http\Responders\Api;

use App\Http\Resources\CalendarEntryResource;
use App\Models\Account;
use App\Services\CalendarService;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ShowCalendarEntryResponder
{
    public function __construct(public CalendarService $calendarService) {}

    public function __invoke(Request $request, Account $account, string $eventId): CalendarEntryResource
    {
        abort_unless($account->user_id === $request->user()->id, 404);
        abort_unless($account->isGoogleCalendarConnected(), 404);

        try {
            $entry = $this->calendarService->getEntry($account, $eventId);

            return new CalendarEntryResource($entry);
        } catch (\Throwable $e) {
            if ($e instanceof HttpException) {
                throw $e;
            }

            abort(503, 'Unable to fetch calendar entry.');
        }
    }
}
