<?php

declare(strict_types=1);

namespace Module\Actions\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Module\Actions\Services\ActionScheduleService;

final class ActionScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'cron' => ['nullable', 'string', 'max:120', $this->cronRule()],
            'timezone' => ['nullable', 'timezone'],
            'input' => ['nullable', 'array'],
            'options' => ['nullable', 'array'],
            'settings' => ['nullable', 'array'],
        ];
    }

    private function cronRule(): ValidationRule
    {
        return new class implements ValidationRule {
            public function validate(string $attribute, mixed $value, \Closure $fail): void
            {
                if ($value === null || $value === '') {
                    return;
                }

                if (!is_string($value) || !ActionScheduleService::isValidCron($value)) {
                    $fail('Поле :attribute должно быть валидным cron-выражением.');
                }
            }
        };
    }
}
