<?php

declare(strict_types=1);

namespace Module\Projects\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ProjectDestroyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
