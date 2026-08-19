<?php

namespace App\Http\Resources\GitHub;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GitHubIssueResource extends JsonResource
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
            'html_url' => $this->resource['html_url'],
            'body' => $this->resource['body'] ?? null,
            'user' => [
                'login' => $this->resource['user']['login'] ?? null,
            ],
            'labels' => array_map(fn (array $label) => [
                'name' => $label['name'],
                'color' => $label['color'],
            ], $this->resource['labels'] ?? []),
            'created_at' => $this->resource['created_at'],
            'updated_at' => $this->resource['updated_at'],
            'closed_at' => $this->resource['closed_at'] ?? null,
        ];
    }
}
