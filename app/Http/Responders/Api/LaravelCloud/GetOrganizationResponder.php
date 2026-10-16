<?php

namespace App\Http\Responders\Api\LaravelCloud;

use App\Http\Resources\LaravelCloud\OrganizationResource;
use App\Models\Account;
use App\Services\LaravelCloudService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GetOrganizationResponder
{
    public function __invoke(Request $request, Account $account): OrganizationResource|JsonResponse
    {
        $data = (new LaravelCloudService($account))->getOrganization();

        if (empty($data)) {
            abort(404);
        }

        return new OrganizationResource($data);
    }
}
