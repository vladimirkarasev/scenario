<?php

declare(strict_types=1);

namespace Module\Groups\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class GroupMemberStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }
}
