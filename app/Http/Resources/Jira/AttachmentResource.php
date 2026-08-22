<?php

namespace App\Http\Resources\Jira;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttachmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource['id'],
            'filename' => $this->resource['filename'] ?? null,
            'mime_type' => $this->resource['mimeType'] ?? null,
            'size' => $this->resource['size'] ?? null,
            'content' => $this->resource['content'] ?? null,
            'thumbnail' => $this->resource['thumbnail'] ?? null,
        ];
    }
}
