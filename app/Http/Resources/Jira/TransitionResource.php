<?php

namespace App\Http\Resources\Jira;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransitionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'],
            'name' => $this->resource['name'],
            'to' => [
                'name' => $this->resource['to']['name'] ?? null,
                'status_category' => $this->resource['to']['statusCategory']['name'] ?? null,
            ],
        ];
    }
}
