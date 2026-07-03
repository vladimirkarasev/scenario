<?php

declare(strict_types=1);

namespace Module\Directories\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateDirectoryVersionCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:100', 'regex:/^[a-zA-Z0-9_\-\.\/]+$/'],
        ];
    }
}
