<?php

declare(strict_types=1);

namespace Module\Proxy\DTO;

/** @phpstan-consistent-constructor */
class ProxyField
{
    private ?string $label = null;

    private ?string $source = null;

    private string $type = 'string';

    private bool $required = false;

    private bool $nullable = false;

    private mixed $default = null;

    /** @var array<int, string> */
    private array $rules = [];

    private mixed $example = null;

    private ?string $description = null;

    private bool $filterable = false;

    private ?string $sourceFilterable = null;

    private bool $secret = false;

    private bool $identity = false;

    protected function __construct(private readonly string $key)
    {
    }

    public static function make(string $key): static
    {
        return new static($key);
    }

    public function label(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function source(string $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function type(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function required(): static
    {
        $this->required = true;
        $this->nullable = false;

        return $this;
    }

    public function nullable(): static
    {
        $this->nullable = true;
        $this->required = false;

        return $this;
    }

    public function default(mixed $value): static
    {
        $this->default = $value;

        return $this;
    }

    /** @param  array<int, string>|string  $rules */
    public function rules(array|string $rules): static
    {
        $this->rules = is_array($rules) ? array_values($rules) : explode('|', $rules);

        return $this;
    }

    public function example(mixed $value): static
    {
        $this->example = $value;

        return $this;
    }

    public function description(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function filterable(?string $sourceFilterable = null): static
    {
        $this->filterable = true;
        $this->sourceFilterable = $sourceFilterable;

        return $this;
    }

    public function secret(): static
    {
        $this->secret = true;

        return $this;
    }

    public function identity(): static
    {
        $this->identity = true;

        return $this;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function sourcePath(): string
    {
        return $this->source ?? 'payload.'.$this->key;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function isNullable(): bool
    {
        return $this->nullable;
    }

    public function isFilterable(): bool
    {
        return $this->filterable;
    }

    public function isSecret(): bool
    {
        return $this->secret;
    }

    public function isIdentity(): bool
    {
        return $this->identity;
    }

    public function filterKey(): string
    {
        return $this->sourceFilterable ?? $this->key;
    }

    public function defaultValue(): mixed
    {
        return $this->default;
    }

    /** @return array<int, string> */
    public function validationRules(): array
    {
        return $this->rules;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label ?? $this->key,
            'source' => $this->sourcePath(),
            'type' => $this->type,
            'required' => $this->required,
            'nullable' => $this->nullable,
            'default' => $this->default,
            'rules' => $this->rules,
            'example' => $this->example,
            'description' => $this->description,
            'filterable' => $this->filterable,
            'filter_key' => $this->filterable ? ($this->sourceFilterable ?? $this->key) : null,
            'secret' => $this->secret,
            'identity' => $this->identity,
        ];
    }
}
