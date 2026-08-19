<?php

namespace App\Http\Resources\GitHub;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GitHubRepoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'],
            'name' => $this->resource['name'],
            'full_name' => $this->resource['full_name'],
            'description' => $this->resource['description'],
            'private' => $this->resource['private'],
            'html_url' => $this->resource['html_url'],
            'default_branch' => $this->resource['default_branch'],
            'updated_at' => $this->resource['updated_at'],
        ];
    }
}
