<?php

namespace App\Http\Responders\Api;

use App\Http\Resources\DashboardMessageResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListDashboardMessagesResponder
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $query = $request->user()->dashboardMessages()->latest();

        if ($request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }

        if (! $request->boolean('include_archived')) {
            $query->whereNull('archived_at');
        }

        return DashboardMessageResource::collection($query->paginate());
    }
}
