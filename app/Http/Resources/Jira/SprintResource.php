<?php

namespace App\Http\Resources\Jira;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SprintResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'],
            'name' => $this->resource['name'],
            'state' => $this->resource['state'] ?? null,
            'start_date' => $this->resource['startDate'] ?? null,
            'end_date' => $this->resource['endDate'] ?? null,
            'goal' => $this->resource['goal'] ?? null,
        ];
    }
}
