<?php

namespace App\Http\Resources\LaravelCloud;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UsageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
