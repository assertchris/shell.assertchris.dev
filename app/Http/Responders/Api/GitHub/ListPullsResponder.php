<?php

namespace App\Http\Responders\Api\GitHub;

use App\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ListPullsResponder
{
    public function __invoke(Request $request, Account $account): JsonResponse
    {
        return response()->json([]);
    }
}
