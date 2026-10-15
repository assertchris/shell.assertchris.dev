<?php

namespace App\Http\Resources\LaravelCloud;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EnvironmentResource extends JsonResource
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
            'status' => $attributes['status'] ?? null,
            'color' => $attributes['color'] ?? null,
            'vanity_domain' => $attributes['vanity_domain'] ?? null,
            'framework' => $attributes['framework'] ?? null,
            'language' => $attributes['language'] ?? null,
            'php_major_version' => $attributes['php_major_version'] ?? null,
            'serving' => $attributes['serving'] ?? null,
            'uses_octane' => $attributes['uses_octane'] ?? null,
            'uses_hibernation' => $attributes['uses_hibernation'] ?? null,
            'timeout' => $attributes['timeout'] ?? null,
            'shutdown_timeout' => $attributes['shutdown_timeout'] ?? null,
            'uses_push_to_deploy' => $attributes['uses_push_to_deploy'] ?? null,
            'uses_deploy_hook' => $attributes['uses_deploy_hook'] ?? null,
            'network_settings' => $attributes['network_settings'] ?? null,
            'created_at' => $attributes['created_at'] ?? null,
        ];
    }
}
