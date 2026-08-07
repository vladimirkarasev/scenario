<?php

declare(strict_types=1);

namespace App\Http\Requests\Expression;

use App\Rules\Expression\RenderExpressionBatchRules;
use Illuminate\Foundation\Http\FormRequest;

final class RenderExpressionBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return RenderExpressionBatchRules::get();
    }
}
