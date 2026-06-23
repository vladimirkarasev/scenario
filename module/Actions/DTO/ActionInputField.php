<?php

declare(strict_types=1);

namespace Module\Actions\DTO;

final readonly class ActionInputField
{
    /** Тип: string|number|boolean|uuid|email */
    public function __construct(
        public string $key,
        public string $label,
        public string $type,
        public bool $required,
        public mixed $default = null,
    ) {
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        $key = is_string($data['key'] ?? null) ? (string)$data['key'] : '';
        $label = is_string($data['label'] ?? null) && $data['label'] !== '' ? (string)$data['label'] : $key;
        $type = is_string($data['type'] ?? null) ? (string)$data['type'] : 'string';

        return new self(
            key: $key,
            label: $label,
            type: $type,
            required: (bool)($data['required'] ?? false),
            default: $data['default'] ?? null,
        );
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
        ];
    }
}
