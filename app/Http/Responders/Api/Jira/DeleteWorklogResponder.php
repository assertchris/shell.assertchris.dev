<?php

namespace App\Http\Responders\Api\Jira;

use App\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeleteWorklogResponder
{
    public function __invoke(Request $request, Account $account): JsonResponse
    {
        abort(501);
    }
}
