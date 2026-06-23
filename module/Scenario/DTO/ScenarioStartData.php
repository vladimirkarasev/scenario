<?php

declare(strict_types=1);

namespace Module\Scenario\DTO;

use Module\Scenario\Http\Requests\StartScenarioRunnerRequest;

final readonly class ScenarioStartData
{
    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $userData
     */
    public function __construct(
        public ?string $scenarioId,
        public ?string $versionId,
        public ?string $alias,
        public array $context,
        public array $userData,
    ) {}

    public static function fromRequest(StartScenarioRunnerRequest $request): self
    {
        return new self(
            scenarioId: $request->filled('scenario_id') ? $request->string('scenario_id')->toString() : null,
            versionId: $request->filled('version_id') ? $request->string('version_id')->toString() : null,
            alias: $request->filled('alias') ? $request->string('alias')->toString() : null,
            context: self::arrayInput($request->input('context')),
            userData: self::arrayInput($request->input('user_data')),
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
