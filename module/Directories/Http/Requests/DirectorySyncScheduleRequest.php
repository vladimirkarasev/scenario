<?php

declare(strict_types=1);

namespace Module\Directories\Http\Requests;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Module\Directories\Services\DirectorySyncScheduleService;

final class DirectorySyncScheduleRequest extends FormRequest
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
        ];
    }

    private function cronRule(): ValidationRule
    {
        return new class implements ValidationRule
        {
            public function validate(string $attribute, mixed $value, Closure $fail): void
            {
                if ($value === null || $value === '') {
                    return;
                }

                if (!is_string($value) || !DirectorySyncScheduleService::isValidCron($value)) {
                    $fail('Поле :attribute должно быть валидным cron-выражением.');
                }
            }
        };
    }
}
