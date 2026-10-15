<?php

namespace App\Http\Resources\LaravelCloud;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = $this->resource['data'] ?? $this->resource;
        $attributes = $data['attributes'] ?? [];

        return [
            'id' => $data['id'],
            'name' => $attributes['name'] ?? null,
            'slug' => $attributes['slug'] ?? null,
        ];
    }
}
