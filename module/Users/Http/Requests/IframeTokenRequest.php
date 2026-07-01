<?php

declare(strict_types=1);

namespace Module\Users\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class IframeTokenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'external_id' => ['required', 'string', 'max:255'],
        ];
    }
}
