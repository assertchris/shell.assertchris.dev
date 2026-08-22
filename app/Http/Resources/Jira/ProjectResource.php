<?php

namespace App\Http\Resources\Jira;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'],
            'key' => $this->resource['key'],
            'name' => $this->resource['name'],
            'description' => $this->resource['description'] ?? null,
            'projectTypeKey' => $this->resource['projectTypeKey'] ?? null,
            'style' => $this->resource['style'] ?? null,
            'isPrivate' => $this->resource['isPrivate'] ?? null,
        ];
    }
}
