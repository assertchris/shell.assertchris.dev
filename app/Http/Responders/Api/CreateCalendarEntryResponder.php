<?php

namespace App\Http\Responders\Api;

use App\Http\Resources\CalendarEntryResource;
use App\Models\Account;
use App\Services\CalendarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CreateCalendarEntryResponder
{
    public function __construct(public CalendarService $calendarService) {}

    public function __invoke(Request $request, Account $account): JsonResponse
    {
        $validated = $request->validate([
            'summary' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'location' => ['nullable', 'string'],
            'start' => ['required', 'array'],
            'start.dateTime' => ['required', 'string', 'date_format:Y-m-d\TH:i:sP'],
            'start.timeZone' => ['required', 'string'],
            'end' => ['required', 'array'],
            'end.dateTime' => ['required', 'string', 'date_format:Y-m-d\TH:i:sP'],
            'end.timeZone' => ['required', 'string'],
        ]);

        try {
            $entry = $this->calendarService->createEntry($account, $validated);

            return (new CalendarEntryResource($entry))
                ->response()
                ->setStatusCode(Response::HTTP_CREATED);
        } catch (\Throwable $e) {
            if ($e instanceof HttpException) {
                throw $e;
            }

            abort(503, 'Unable to create calendar entry.');
        }
    }
}
