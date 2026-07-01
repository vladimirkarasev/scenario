<?php

declare(strict_types=1);

namespace Module\Proxy\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ProxyFeedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'filter.parent_id' => ['nullable', 'string'],
            'filter.search' => ['nullable', 'string', 'max:255'],
            'filter.type' => ['nullable', 'string'],
            'page.number' => ['nullable', 'integer', 'min:1'],
            'page.size' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
