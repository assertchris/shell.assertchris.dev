<?php

namespace App\Http\Resources\Jira;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FilterResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'],
            'name' => $this->resource['name'],
            'description' => $this->resource['description'] ?? null,
            'jql' => $this->resource['jql'] ?? null,
            'owner' => [
                'account_id' => $this->resource['owner']['accountId'] ?? null,
                'display_name' => $this->resource['owner']['displayName'] ?? null,
            ],
            'favourite' => $this->resource['favourite'] ?? null,
        ];
    }
}
