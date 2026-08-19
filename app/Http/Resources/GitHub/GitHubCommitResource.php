<?php

namespace App\Http\Resources\GitHub;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GitHubCommitResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'sha' => $this->resource['sha'],
            'commit' => [
                'message' => $this->resource['commit']['message'] ?? null,
                'author' => $this->resource['commit']['author'] ?? null,
            ],
            'html_url' => $this->resource['html_url'],
        ];
    }
}
