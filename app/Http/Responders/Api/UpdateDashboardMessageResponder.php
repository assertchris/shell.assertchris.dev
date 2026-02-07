<?php

namespace App\Http\Responders\Api;

use App\Http\Requests\UpdateDashboardMessageRequest;
use App\Http\Resources\DashboardMessageResource;
use App\Models\DashboardMessage;

class UpdateDashboardMessageResponder
{
    public function __invoke(UpdateDashboardMessageRequest $request, DashboardMessage $message): DashboardMessageResource
    {
        abort_unless($message->user_id === $request->user()->id, 404);

        if ($request->has('is_read')) {
            $message->read_at = $request->boolean('is_read') ? now() : null;
        }

        if ($request->has('is_archived')) {
            $message->archived_at = $request->boolean('is_archived') ? now() : null;
        }

        $message->save();

        return new DashboardMessageResource($message->fresh());
    }
}
