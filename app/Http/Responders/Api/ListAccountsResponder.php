<?php

namespace App\Http\Responders\Api;

use App\Http\Resources\AccountResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ListAccountsResponder
{
    public function __invoke(Request $request): AnonymousResourceCollection
    {
        $accounts = $request->user()
            ->accounts()
            ->where('is_active', true)
            ->get();

        return AccountResource::collection($accounts);
    }
}
