<?php

namespace App\Http\Resources;

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
            'filename' => $this->resource->filename(),
            'content_type' => $this->resource->contentType(),
            'content_id' => $this->resource->contentId(),
            'extension' => $this->resource->extension(),
        ];
    }
}
