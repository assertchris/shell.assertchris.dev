<?php

namespace App\Http\Resources\LaravelCloud;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CommandResource extends JsonResource
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
            'command' => $attributes['command'] ?? null,
            'output' => $attributes['output'] ?? null,
            'status' => $attributes['status'] ?? null,
            'exit_code' => $attributes['exit_code'] ?? null,
            'failure_reason' => $attributes['failure_reason'] ?? null,
            'started_at' => $attributes['started_at'] ?? null,
            'finished_at' => $attributes['finished_at'] ?? null,
            'created_at' => $attributes['created_at'] ?? null,
        ];
    }
}
