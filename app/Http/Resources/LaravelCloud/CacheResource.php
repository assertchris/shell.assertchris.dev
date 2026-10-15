<?php

namespace App\Http\Resources\LaravelCloud;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CacheResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = $this->resource['data'];
        $attributes = $data['attributes'];

        return [
            'id' => $data['id'],
            'name' => $attributes['name'] ?? null,
            'type' => $attributes['type'] ?? null,
            'status' => $attributes['status'] ?? null,
            'region' => $attributes['region'] ?? null,
            'size' => $attributes['size'] ?? null,
            'auto_upgrade_enabled' => $attributes['auto_upgrade_enabled'] ?? null,
            'is_public' => $attributes['is_public'] ?? null,
            'eviction_policy' => $attributes['eviction_policy'] ?? null,
            'uses_hibernation' => $attributes['uses_hibernation'] ?? null,
            'hibernation_timeout' => $attributes['hibernation_timeout'] ?? null,
        ];
    }
}
