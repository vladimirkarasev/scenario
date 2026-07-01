<?php

declare(strict_types=1);

namespace App\Http\Requests\EmbedAuth;

use Illuminate\Foundation\Http\FormRequest;

final class EmbedAuthExchangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            '_token' => ['required', 'string'],
        ];
    }
}
