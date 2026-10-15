<?php

namespace App\Http\Resources\LaravelCloud;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstanceResource extends JsonResource
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
            'status' => $attributes['status'] ?? null,
            'region' => $attributes['region'] ?? null,
            'created_at' => $attributes['created_at'] ?? null,
        ];
    }
}
