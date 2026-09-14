<?php

declare(strict_types=1);

namespace Module\Scenario\Services\Nodes\Block;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Factory;
use Module\Scenario\Services\Nodes\Block\Rules\GrzRule;
use Module\Scenario\Services\Nodes\Block\Rules\MapPointRule;
use Module\Scenario\Services\Nodes\Block\Rules\RouteRule;
use Module\Scenario\Services\Nodes\Block\Rules\VinRule;
use Module\Scenario\Services\Nodes\NodeDataReader;

final readonly class BlockNodeValidator
{
    private const array MESSAGES = [
        'required' => 'Поле обязательно для заполнения.',
        'accepted' => 'Поле обязательно для заполнения.',
        'email' => 'Введите корректный email адрес.',
        'numeric' => 'Значение должно быть числом.',
        'date' => 'Введите корректную дату.',
        'max' => 'Превышено максимальное количество символов: :max.',
    ];

    public function __construct(
        private NodeDataReader $nodeData,
        private Factory $validation,
    ) {
    }

    /**
     * @param  array<string, mixed>  $nodeData
     * @param  array<string, mixed>  $input
     */
    public function validate(array $nodeData, array $input): void
    {
        $rules = $this->buildRules($nodeData);

        if (empty($rules)) {
            return;
        }

        $this->validation->make($input, $rules, self::MESSAGES)->validate();
    }

    /**
     * @param  array<string, mixed>  $nodeData
     * @return array<string, array<int, string|ValidationRule>>
     */
    private function buildRules(array $nodeData): array
    {
        $rules = [];

        foreach ($this->nodeData->array($nodeData, 'fields') as $field) {
            if (!is_array($field)) {
                continue;
            }

            $name = $this->nodeData->string($field, 'name');

            if ($name === '') {
                continue;
            }

            $rules[$name] = $this->fieldRules($field);
        }

        return $rules;
    }

    /**
     * @param  array<array-key, mixed>  $field
     * @return array<int, string|ValidationRule>
     */
    private function fieldRules(array $field): array
    {
        $type = $this->nodeData->string($field, 'type', 'input');
        $required = $this->nodeData->boolean($field, 'required');

        if ($type === 'vin') {
            return [new VinRule($required)];
        }

        if ($type === 'grz') {
            return [new GrzRule($required)];
        }

        if ($type === 'map_point') {
            return [new MapPointRule($required)];
        }

        if ($type === 'route') {
            return [new RouteRule($required)];
        }

        $rules = [];

        $rules[] = $required
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
            $max = $this->nodeData->integer($field, 'maxLength', 3000);
            $rules[] = "max:{$max}";
        }

        return $rules;
    }
}
