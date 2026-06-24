<?php

declare(strict_types=1);

namespace Module\Actions\DTO;

use Illuminate\Http\Request;
use Module\Actions\Models\Action;

final readonly class ActionData
{
    /**
     * @param array<string, mixed>|null  $config
     * @param array<string, mixed>|null  $schema
     * @param array<string, mixed>|null  $uiSchema
     * @param list<array<string, mixed>> $inputFields
     * @param list<string>               $categoryIds
     */
    public function __construct(
        public string $name,
        public string $slug,
        public string $code,
        public ?string $description,
        public string $type,
        public bool $isActive,
        public ?array $config,
        public ?array $schema,
        public ?array $uiSchema,
        public array $inputFields,
        public array $categoryIds,
        public bool $canManageActions,
    ) {}

    public static function fromRequest(Request $request, ?Action $action = null): self
    {
        return new self(
            name: $request->string('name')->toString(),
            slug: $request->string('slug')->toString(),
            code: $request->string('code')->toString(),
            description: $request->filled('description') ? $request->string('description')->toString() : null,
            type: $request->string('type')->toString(),
            isActive: $request->boolean('is_active'),
            config: self::stringKeyedArray($request->input('config')),
            schema: self::stringKeyedArray($request->input('schema')),
            uiSchema: self::stringKeyedArray($request->input('ui_schema')),
            inputFields: self::inputFieldsList($request->input('input_fields')),
            categoryIds: self::stringList($request->input('category_ids')),
            canManageActions: $request->user() !== null,
        );
    }

    /** @return array<string, mixed> */
    public function toAttributes(): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'code' => $this->code,
            'description' => $this->description,
            'type' => $this->type,
            'is_active' => $this->isActive,
            'config' => $this->config,
            'schema' => $this->schema,
            'ui_schema' => $this->uiSchema,
            'input_fields' => $this->inputFields,
        ];
    }

    /** @return list<string> */
    private static function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, is_string(...)));
    }

    /** @return list<array<string, mixed>> */
    private static function inputFieldsList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $result = [];

        foreach ($value as $item) {
            if (! is_array($item)) {
                continue;
            }

            $key = $item['key'] ?? null;

            if (! is_string($key) || $key === '') {
                continue;
            }

            $result[] = ActionInputField::fromArray(self::stringKeyedArray($item) ?? [])->toArray();
        }

        return $result;
    }

    /** @return array<string, mixed>|null */
    private static function stringKeyedArray(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $result = [];

        foreach ($value as $key => $item) {
            $result[(string) $key] = $item;
        }

        return $result;
    }
}
