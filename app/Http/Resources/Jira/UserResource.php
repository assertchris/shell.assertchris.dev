<?php

namespace App\Http\Resources\Jira;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'account_id' => $this->resource['accountId'] ?? null,
            'display_name' => $this->resource['displayName'] ?? null,
            'email_address' => $this->resource['emailAddress'] ?? null,
            'active' => $this->resource['active'] ?? null,
            'avatar_urls' => $this->resource['avatarUrls'] ?? null,
        ];
    }
}
