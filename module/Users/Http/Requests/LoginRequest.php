<?php

declare(strict_types=1);

namespace Module\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'project_id' => [
                'nullable',
                'string',
                'uuid',
                Rule::exists('projects', 'id')->where('is_active', true),
            ],
        ];
    }
}
