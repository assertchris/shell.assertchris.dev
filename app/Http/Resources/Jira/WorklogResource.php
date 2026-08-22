<?php

namespace App\Http\Resources\Jira;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorklogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'],
            'author' => [
                'account_id' => $this->resource['author']['accountId'] ?? null,
                'display_name' => $this->resource['author']['displayName'] ?? null,
            ],
            'time_spent_seconds' => $this->resource['timeSpentSeconds'] ?? null,
            'started' => $this->resource['started'] ?? null,
            'comment' => $this->resource['comment'] ?? null,
        ];
    }
}
