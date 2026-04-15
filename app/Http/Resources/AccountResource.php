<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'mail_used' => $this->mail_used,
            'google_calendar_used' => $this->google_calendar_used,
            'is_active' => $this->is_active,
        ];
    }
}
