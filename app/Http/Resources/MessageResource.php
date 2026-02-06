<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uid' => $this->resource->uid(),
            'subject' => $this->resource->subject(),
            'from' => $this->resource->from()?->toArray(),
            'to' => array_map(fn ($a) => $a->toArray(), $this->resource->to()),
            'date' => $this->resource->date()?->toIso8601String(),
            'has_attachments' => $this->resource->hasAttachments(),
            'cc' => array_map(fn ($a) => $a->toArray(), $this->resource->cc()),
            'text' => $this->resource->text(),
            'html' => $this->resource->html(),
            'attachment_count' => $this->resource->attachmentCount(),
        ];
    }
}
