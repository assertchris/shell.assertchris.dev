<?php

namespace App\Http\Resources\LaravelCloud;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationResource extends JsonResource
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
            'region' => $attributes['region'] ?? null,
            'root_directory' => $attributes['root_directory'] ?? null,
            'slack_channel' => $attributes['slack_channel'] ?? null,
            'avatar_url' => $attributes['avatar_url'] ?? null,
            'framework' => $attributes['framework'] ?? null,
            'language' => $attributes['language'] ?? null,
            'created_at' => $attributes['created_at'] ?? null,
            'repository' => [
                'full_name' => $attributes['repository']['full_name'] ?? null,
                'default_branch' => $attributes['repository']['default_branch'] ?? null,
            ],
        ];
    }
}
