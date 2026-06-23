<?php

declare(strict_types=1);

namespace Module\Scenario\DTO;

use Module\Scenario\Http\Requests\DispatchScenarioRequest;

final readonly class ScenarioDispatchData
{
    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $userData
     */
    public function __construct(
        public ?string $login,
        public ?string $externalId,
        public string $tag,
        public array $context,
        public array $userData,
    ) {
    }

    public static function fromRequest(DispatchScenarioRequest $request): self
    {
        return new self(
            login: $request->filled('login') ? $request->string('login')->toString() : null,
            externalId: $request->filled('external_id') ? $request->string('external_id')->toString() : null,
            tag: $request->string('tag')->toString(),
            context: self::arrayInput($request->input('context')),
            userData: self::arrayInput($request->input('user_data')),
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
