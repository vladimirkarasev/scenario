<?php

declare(strict_types=1);

namespace Module\Proxy\DTO;

final class ProxyFieldList extends ProxyField
{
    /** @var list<string> */
    private array $values = [];

    protected function __construct(string $key)
    {
        parent::__construct($key);
        $this->type('list');
    }

    /** @param  string  $value */
    #[\Override]
    public function default(mixed $value): static
    {
        return parent::default($value);
    }

    /** @param  list<string>  $values */
    public function values(array $values): static
    {
        $this->values = $values;

        return $this;
    }

    /** @return array<int, string> */
    #[\Override]
    public function validationRules(): array
    {
        $rules = parent::validationRules();

        if ($this->values !== []) {
            $rules[] = 'in:'.implode(',', $this->values);
        }

        return $rules;
    }
}
