<?php

namespace App\Http\Requests\LaravelCloud;

use Illuminate\Foundation\Http\FormRequest;

class ListEnvironmentLogsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'from' => ['required', 'date'],
            'to' => ['required', 'date'],
            'type' => ['sometimes', 'string'],
            'query' => ['sometimes', 'string'],
            'cursor' => ['sometimes', 'string'],
        ];
    }
}
