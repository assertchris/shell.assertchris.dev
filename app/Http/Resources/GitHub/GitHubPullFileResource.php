<?php

namespace App\Http\Resources\GitHub;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GitHubPullFileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'filename' => $this->resource['filename'],
            'status' => $this->resource['status'],
            'additions' => $this->resource['additions'],
            'deletions' => $this->resource['deletions'],
            'changes' => $this->resource['changes'],
            'patch' => $this->resource['patch'] ?? null,
        ];
    }
}
