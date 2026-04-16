<?php

namespace App\Http\Responders\Api;

use App\Http\Resources\CalendarEntrySummaryResource;
use App\Models\Account;
use App\Services\CalendarService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ListCalendarEntriesResponder
{
    public function __construct(public CalendarService $calendarService) {}

    public function __invoke(Request $request, Account $account): AnonymousResourceCollection
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = min(50, max(1, (int) $request->query('per_page', 25)));

        try {
            $result = $this->calendarService->listEntries($account, $perPage);

            return CalendarEntrySummaryResource::collection($result['items'])
                ->additional([
                    'meta' => [
                        'page' => $page,
                        'per_page' => $perPage,
                        'next_page_token' => $result['nextPageToken'],
                    ],
                ]);
        } catch (\Throwable $e) {
            if ($e instanceof HttpException) {
                throw $e;
            }

            abort(503, 'Unable to fetch calendar entries.');
        }
    }
}
