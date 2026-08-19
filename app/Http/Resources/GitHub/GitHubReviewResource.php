<?php

namespace App\Http\Resources\GitHub;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GitHubReviewResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'],
            'user' => [
                'login' => $this->resource['user']['login'] ?? null,
            ],
            'state' => $this->resource['state'],
            'body' => $this->resource['body'],
            'submitted_at' => $this->resource['submitted_at'],
        ];
    }
}
