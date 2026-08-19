<?php

namespace App\Http\Resources\GitHub;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GitHubPullResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'number' => $this->resource['number'],
            'title' => $this->resource['title'],
            'state' => $this->resource['state'],
            'draft' => $this->resource['draft'] ?? false,
            'html_url' => $this->resource['html_url'],
            'user' => [
                'login' => $this->resource['user']['login'] ?? null,
                'avatar_url' => $this->resource['user']['avatar_url'] ?? null,
            ],
            'created_at' => $this->resource['created_at'],
            'updated_at' => $this->resource['updated_at'],
            'merged_at' => $this->resource['merged_at'] ?? null,
            'head' => [
                'ref' => $this->resource['head']['ref'] ?? null,
                'sha' => $this->resource['head']['sha'] ?? null,
            ],
            'base' => [
                'ref' => $this->resource['base']['ref'] ?? null,
                'sha' => $this->resource['base']['sha'] ?? null,
            ],
        ];
    }
}
