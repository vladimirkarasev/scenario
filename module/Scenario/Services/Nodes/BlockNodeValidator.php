<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes;

use Illuminate\Support\Facades\Validator;

final class BlockNodeValidator
{
    use NodeHelpers;

    private const MESSAGES = [
        'required' => 'Поле обязательно для заполнения.',
        'accepted' => 'Поле обязательно для заполнения.',
        'email' => 'Введите корректный email адрес.',
        'numeric' => 'Значение должно быть числом.',
        'date' => 'Введите корректную дату.',
        'max' => 'Превышено максимальное количество символов: :max.',
    ];

    /**
     * @param array<string, mixed> $nodeData
     * @param array<string, mixed> $input
     */
    public function validate(array $nodeData, array $input): void
    {
        $rules = $this->buildRules($nodeData);

        if (empty($rules)) {
            return;
        }

        Validator::make($input, $rules, self::MESSAGES)->validate();
    }

    /**
     * @param  array<string, mixed>              $nodeData
     * @return array<string, array<int, string>>
     */
    private function buildRules(array $nodeData): array
    {
        $rules = [];

        foreach ($this->arrayField($nodeData, 'fields') as $field) {
            if (! is_array($field)) {
                continue;
            }

            $name = $this->strField($field, 'name');

            if ($name === '') {
                continue;
            }

            $rules[$name] = $this->fieldRules($field);
        }

        return $rules;
    }

    /**
     * @param  array<array-key, mixed> $field
     * @return array<int, string>
     */
    private function fieldRules(array $field): array
    {
        $type = $this->strField($field, 'type', 'input');
        $rules = [];

        $rules[] = $this->boolField($field, 'required')
            ? ($type === 'checkbox' ? 'accepted' : 'required')
            : 'nullable';

        $typeRule = match ($type) {
            'email' => 'email',
            'number' => 'numeric',
            'date' => 'date',
            'datetime' => 'date',
            default => null,
        };

        if ($typeRule !== null) {
            $rules[] = $typeRule;
        }

        if ($type === 'textarea') {
            $max = $this->intField($field, 'maxLength', 3000);
            $rules[] = "max:{$max}";
        }

        return $rules;
    }
}
