<?php

declare(strict_types=1);

namespace Module\Actions\DTO;

use Illuminate\Http\Request;
use Module\Actions\Models\ActionCredential;

final readonly class ActionCredentialData
{
    /**
     * @param array<string, mixed>|null $config
     * @param array<string, mixed>      $secrets
     */
    public function __construct(
        public string $name,
        public string $type,
        public ?array $config,
        public array $secrets,
        public bool $canManageActions,
    ) {}

    public static function fromRequest(Request $request, ?ActionCredential $credential = null): self
    {
        return new self(
            name: $request->string('name')->toString(),
            type: $request->string('type')->toString(),
            config: self::stringKeyedArray($request->input('config')),
            secrets: self::stringKeyedArray($request->input('secrets')) ?? [],
            canManageActions: $request->user() !== null,
        );
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
