<?php

declare(strict_types=1);

namespace Module\Actions\DTO;

final class ActionConfigField
{
    private string $label;

    private string $type = 'string';

    private bool $required = false;

    private mixed $default = null;

    private ?string $placeholder = null;

    private ?string $description = null;

    private bool $multiline = false;

    /** @var list<array{value: string, label: string}> */
    private array $options = [];

    private function __construct(private readonly string $key)
    {
        $this->label = $key;
    }

    public static function make(string $key): self
    {
        return new self($key);
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /** Тип: string|text|code|number|boolean|select|email_list|key_value */
    public function type(string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function required(bool $required = true): self
    {
        $this->required = $required;

        return $this;
    }

    public function default(mixed $value): self
    {
        $this->default = $value;

        return $this;
    }

    public function placeholder(string $placeholder): self
    {
        $this->placeholder = $placeholder;

        return $this;
    }

    public function description(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function multiline(bool $multiline = true): self
    {
        $this->multiline = $multiline;

        return $this;
    }

    /** @param  list<array{value: string, label: string}>  $options */
    public function options(array $options): self
    {
        $this->options = $options;

        return $this;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'type' => $this->type,
            'required' => $this->required,
            'default' => $this->default,
            'placeholder' => $this->placeholder,
            'description' => $this->description,
            'multiline' => $this->multiline,
            'options' => $this->options,
        ];
    }
}
