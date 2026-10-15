<?php

namespace App\Http\Resources\LaravelCloud;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeploymentResource extends JsonResource
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
            'slug' => $attributes['slug'] ?? null,
            'status' => $attributes['status'] ?? null,
            'branch_name' => $attributes['branch_name'] ?? null,
            'commit_hash' => $attributes['commit_hash'] ?? null,
            'commit_message' => $attributes['commit_message'] ?? null,
            'commit_author' => $attributes['commit_author'] ?? null,
            'failure_reason' => $attributes['failure_reason'] ?? null,
            'php_major_version' => $attributes['php_major_version'] ?? null,
            'build_command' => $attributes['build_command'] ?? null,
            'node_version' => $attributes['node_version'] ?? null,
            'uses_octane' => $attributes['uses_octane'] ?? null,
            'uses_hibernation' => $attributes['uses_hibernation'] ?? null,
            'started_at' => $attributes['started_at'] ?? null,
            'finished_at' => $attributes['finished_at'] ?? null,
        ];
    }
}
