<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ProvisionLabRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() === true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'name' => ['sometimes', 'string', 'max:120'],
            'base_image' => ['sometimes', 'string', 'max:160'],
            'profile' => ['sometimes', 'string', 'max:80'],
            'cpu_limit' => ['sometimes', 'integer', 'min:1', 'max:2'],
            'memory_limit' => ['sometimes', 'integer', 'min:256', 'max:2048'],
            'storage_limit' => ['sometimes', 'integer', 'min:5', 'max:50'],
        ];
    }
}
