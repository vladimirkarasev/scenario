<?php

declare(strict_types=1);

namespace App\Http\Requests\EmbedAuth;

use Illuminate\Foundation\Http\FormRequest;

final class EmbedAuthRefreshRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'refresh_token' => ['required', 'string'],
        ];
    }
}
