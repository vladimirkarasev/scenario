<?php

declare(strict_types=1);

namespace Module\Proxy\DTO;

use Module\Proxy\Enums\ProxyEndpointType;
use Module\Proxy\Http\Requests\ProxyEndpointRequest;
use Module\Proxy\Models\ProxyEndpoint;

final readonly class ProxyEndpointData
{
    /**
     * @param list<string> $categoryIds
     * @param array<string, mixed> $credentials
     * @param array<string, mixed> $config
     * @param list<array<string, mixed>> $mockResponses
     */
    public function __construct(
        public string $name,
        public string $code,
        public string $type,
        public ?string $description,
        public bool $isActive,
        public bool $isMocked,
        public string $handlerClass,
        public ?int $connectionId,
        public string $method,
        public array $categoryIds,
        public array $credentials,
        public array $config,
        public array $mockResponses,
        public bool $categoriesProvided,
    ) {}

    public static function fromRequest(
        ProxyEndpointRequest $request,
        ?ProxyEndpoint $current = null,
    ): self {
        $currentType = $current instanceof ProxyEndpoint ? $current->type->value : ProxyEndpointType::Webhook->value;
        $currentActive = $current instanceof ProxyEndpoint ? $current->is_active : true;
        $currentMocked = $current instanceof ProxyEndpoint ? $current->is_mocked : false;
        $currentMethod = $current instanceof ProxyEndpoint ? $current->method : 'POST';
        $currentConfig = $current instanceof ProxyEndpoint ? $current->config : [];
        $currentResponses = $current instanceof ProxyEndpoint ? $current->mock_responses : [];

        return new self(
            name: $request->string('name')->toString(),
            code: $request->string('code')->toString(),
            type: $request->string('type', $currentType)->toString(),
            description: $request->filled('description') ? $request->string('description')->toString() : null,
            isActive: $request->boolean('is_active', $currentActive),
            isMocked: $request->boolean('is_mocked', $currentMocked),
            handlerClass: $request->string('handler_class')->toString(),
            connectionId: $request->filled('connection_id') ? $request->integer('connection_id') : null,
            method: $request->string('method', $currentMethod ?? 'POST')->toString(),
            categoryIds: self::strings($request->input('category_ids', [])),
            credentials: self::map($request->input('credentials', [])),
            config: self::map($request->input('config', $currentConfig ?? [])),
            mockResponses: self::listOfMaps($request->input('mock_responses', $currentResponses ?? [])),
            categoriesProvided: $request->exists('category_ids'),
        );
    }

    /** @return list<string> */
    private static function strings(mixed $value): array
    {
        return is_array($value)
            ? array_values(array_filter($value, is_string(...)))
            : [];
    }

    /** @return array<string, mixed> */
    private static function map(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $key => $item) {
            $result[(string) $key] = $item;
        }

        return $result;
    }

    /** @return list<array<string, mixed>> */
    private static function listOfMaps(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $item) {
            if (is_array($item)) {
                $result[] = self::map($item);
            }
        }

        return $result;
    }
}
