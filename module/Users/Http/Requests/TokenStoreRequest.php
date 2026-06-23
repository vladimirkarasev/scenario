<?php

declare(strict_types=1);

namespace Module\Users\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

final class TokenStoreRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ];
    }

    public function tokenName(): string
    {
        return $this->string('name')->toString();
    }

    public function expiresAt(): ?Carbon
    {
        return $this->filled('expires_at')
            ? Carbon::parse($this->string('expires_at')->toString())
            : null;
    }
}
