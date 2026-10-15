<?php

namespace App\Http\Resources\LaravelCloud;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'message' => $this->resource['message'] ?? null,
            'level' => $this->resource['level'] ?? null,
            'type' => $this->resource['type'] ?? null,
            'logged_at' => $this->resource['logged_at'] ?? null,
            'data' => $this->resource['data'] ?? null,
        ];
    }
}
