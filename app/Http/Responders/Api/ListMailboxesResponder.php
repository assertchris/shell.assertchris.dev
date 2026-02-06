<?php

namespace App\Http\Responders\Api;

use App\Http\Resources\MailboxResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListMailboxesResponder
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $mailboxes = $request->user()
            ->mailboxes()
            ->where('is_active', true)
            ->get();

        return MailboxResource::collection($mailboxes);
    }
}
