<?php

declare(strict_types=1);

namespace App\Http\Requests\Expression;

use App\Rules\Expression\RenderExpressionRules;
use Illuminate\Foundation\Http\FormRequest;

final class RenderExpressionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return RenderExpressionRules::get();
    }
}
