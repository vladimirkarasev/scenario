<?php

declare(strict_types=1);

namespace Module\Scenario\DTO;

use Illuminate\Http\Request;

final readonly class ScenarioRunContinueData
{
    /** @param  array<string, mixed>  $input */
    public function __construct(
        public array $input,
        public ?string $selectedTargetNodeId,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            input: self::arrayInput($request->input('input')),
            selectedTargetNodeId: $request->filled('selected_target_node_id')
                ? $request->string('selected_target_node_id')->toString()
                : null,
        );
    }

    /** @return array<string, mixed> */
    private static function arrayInput(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        $result = [];
        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }
}
