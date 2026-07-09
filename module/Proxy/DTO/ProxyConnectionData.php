<?php

declare(strict_types=1);

namespace Module\Proxy\DTO;

use Module\Proxy\Http\Requests\ProxyConnectionRequest;

final readonly class ProxyConnectionData
{
    /** @param array<string, mixed> $values */
    public function __construct(
        public string $name,
        public string $credentialType,
        public array $values,
    ) {}

    public static function fromRequest(ProxyConnectionRequest $request): self
    {
        $values = $request->input('values', []);
        $normalized = [];

        if (is_array($values)) {
            foreach ($values as $key => $value) {
                $normalized[(string) $key] = $value;
            }
        }

        return new self(
            name: $request->string('name')->toString(),
            credentialType: $request->string('credential_type')->toString(),
            values: $normalized,
        );
    }
}
