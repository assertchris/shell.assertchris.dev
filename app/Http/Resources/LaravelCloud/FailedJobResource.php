<?php

namespace App\Http\Resources\LaravelCloud;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FailedJobResource extends JsonResource
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
            'queue' => $attributes['queue'] ?? null,
            'failed_at' => $attributes['failed_at'] ?? null,
            'started_at' => $attributes['started_at'] ?? null,
            'attempts' => $attributes['attempts'] ?? null,
            'exception' => $attributes['exception'] ?? null,
            'retried_at' => $attributes['retried_at'] ?? null,
            'retry_reserved_until' => $attributes['retry_reserved_until'] ?? null,
        ];
    }
}
