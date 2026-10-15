<?php

namespace App\Http\Resources\LaravelCloud;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RegionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'region' => $this->resource['region'] ?? null,
            'label' => $this->resource['label'] ?? null,
            'flag' => $this->resource['flag'] ?? null,
        ];
    }
}
