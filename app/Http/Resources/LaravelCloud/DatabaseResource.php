<?php

namespace App\Http\Resources\LaravelCloud;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DatabaseResource extends JsonResource
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
            'type' => $attributes['type'] ?? null,
            'status' => $attributes['status'] ?? null,
            'region' => $attributes['region'] ?? null,
            'size' => $attributes['size'] ?? null,
            'storage' => $attributes['storage'] ?? null,
            'is_public' => $attributes['is_public'] ?? null,
            'created_at' => $attributes['created_at'] ?? null,
        ];
    }
}
