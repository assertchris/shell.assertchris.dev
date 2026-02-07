<?php

namespace App\Http\Requests;

use App\Enums\DashboardMessagePriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDashboardMessageRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'priority' => ['sometimes', Rule::enum(DashboardMessagePriority::class)],
        ];
    }
}
