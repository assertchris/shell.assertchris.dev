<?php

namespace App\Http\Resources\Jira;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChangelogResource extends JsonResource
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
            'created' => $this->resource['created'] ?? null,
            'items' => array_map(fn (array $item) => [
                'field' => $item['field'] ?? null,
                'from' => $item['from'] ?? null,
                'to' => $item['to'] ?? null,
                'from_string' => $item['fromString'] ?? null,
                'to_string' => $item['toString'] ?? null,
            ], $this->resource['items'] ?? []),
        ];
    }
}
