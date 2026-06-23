<?php

declare(strict_types=1);

namespace Module\Proxy\Services;

use Module\Proxy\DTO\ProxyField;

final class ValidationRulesBuilder
{
    /**
     * @param  iterable<ProxyField>  $fields
     * @return array<string, mixed>
     */
    public function build(iterable $fields): array
    {
        $rules = [];

        foreach ($fields as $field) {
            $fieldRules = $field->validationRules();

            if ($field->isRequired() && !$this->containsPresenceRule($fieldRules)) {
                array_unshift($fieldRules, 'required');
            }

            if ($field->isNullable() && !in_array('nullable', $fieldRules, true)) {
                array_unshift($fieldRules, 'nullable');
            }

            if ($fieldRules !== []) {
                $rules[$field->key()] = $fieldRules;
            }
        }

        return $rules;
    }

    /** @param  array<int, string>  $rules */
    private function containsPresenceRule(array $rules): bool
    {
        return count(array_intersect($rules, ['required', 'present', 'filled'])) > 0;
    }
}
