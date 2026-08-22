<?php

namespace App\Http\Resources\Jira;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommentResource extends JsonResource
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
            'body' => $this->resource['body'] ?? null,
            'created' => $this->resource['created'],
            'updated' => $this->resource['updated'],
        ];
    }
}
