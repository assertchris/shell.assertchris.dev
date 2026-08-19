<?php

namespace App\Http\Resources\GitHub;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GitHubNotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'],
            'reason' => $this->resource['reason'],
            'unread' => $this->resource['unread'],
            'subject' => [
                'title' => $this->resource['subject']['title'] ?? null,
                'type' => $this->resource['subject']['type'] ?? null,
                'url' => $this->resource['subject']['url'] ?? null,
            ],
            'repository' => [
                'full_name' => $this->resource['repository']['full_name'] ?? null,
            ],
            'updated_at' => $this->resource['updated_at'],
        ];
    }
}
