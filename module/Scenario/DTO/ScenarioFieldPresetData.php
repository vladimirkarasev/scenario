<?php

declare(strict_types=1);

namespace Module\Scenario\DTO;

use Illuminate\Http\Request;
use Module\Scenario\Enums\BlockFieldType;

final readonly class ScenarioFieldPresetData
{
    /** @param array<string, mixed> $field */
    public function __construct(
        public string $name,
        public BlockFieldType $fieldType,
        public array $field,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        /** @var array<string, mixed> $field */
        $field = $request->array('field');

        return new self(
            name: $request->string('name')->toString(),
            fieldType: BlockFieldType::from($request->string('field.type')->toString()),
            field: $field,
        );
    }
}
