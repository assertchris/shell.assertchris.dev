<?php

namespace App\Http\Resources\Jira;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BoardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'],
            'name' => $this->resource['name'],
            'type' => $this->resource['type'] ?? null,
            'location' => [
                'project_key' => $this->resource['location']['projectKey'] ?? null,
                'project_name' => $this->resource['location']['projectName'] ?? null,
            ],
        ];
    }
}
