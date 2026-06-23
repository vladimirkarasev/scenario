<?php

declare(strict_types=1);

namespace Module\Scenario\DTO;

use Illuminate\Http\Request;

final readonly class ScenarioRunData
{
    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $userData
     */
    public function __construct(
        public ?string $scenarioId,
        public ?string $scenarioVersionId,
        public array $context,
        public array $userData,
        public ?int $operatorId = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            scenarioId: $request->filled('scenario_id') ? $request->string('scenario_id')->toString() : null,
            scenarioVersionId: $request->filled('scenario_version_id') ? $request->string('scenario_version_id')->toString() : null,
            context: self::arrayInput($request->input('context')),
            userData: self::arrayInput($request->input('user_data')),
            operatorId: $request->filled('operator_id') ? $request->integer('operator_id') : null,
        );
    }

    /** @return array<string, mixed> */
    private static function arrayInput(mixed $value): array
    {
        if (! is_array($value)) {
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
