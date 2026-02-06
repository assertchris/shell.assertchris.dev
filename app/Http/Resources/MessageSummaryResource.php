<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MessageSummaryResource extends JsonResource
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
            'has_attachments' => $this->resource->hasBodyStructure()
                ? $this->resource->bodyStructure()->hasAttachments()
                : false,
        ];
    }
}
