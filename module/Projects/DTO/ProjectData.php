<?php

declare(strict_types=1);

namespace Module\Projects\DTO;

use Illuminate\Http\Request;

final readonly class ProjectData
{
    public function __construct(
        public string $name,
        public string $sitekey,
        public string $host,
        public string $sharedSecret,
        public bool $isActive,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            name: $request->string('name')->toString(),
            sitekey: $request->string('sitekey')->toString(),
            host: $request->string('host')->toString(),
            sharedSecret: $request->string('shared_secret')->toString(),
            isActive: $request->boolean('is_active'),
        );
    }

    /** @return array<string, mixed> */
    public function toAttributes(): array
    {
        return [
            'name' => $this->name,
            'sitekey' => $this->sitekey,
            'host' => $this->host,
            'shared_secret' => $this->sharedSecret,
            'is_active' => $this->isActive,
        ];
    }
}
