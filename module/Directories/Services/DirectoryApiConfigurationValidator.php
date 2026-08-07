<?php

declare(strict_types=1);

namespace Module\Directories\Services;

use Illuminate\Validation\ValidationException;
use Module\Directories\DTO\DirectoryData;
use Module\Directories\Models\Directory;
use Module\Proxy\Models\ProxyEndpoint;
use Module\Proxy\Services\ProxyResponseFieldCatalog;

final readonly class DirectoryApiConfigurationValidator
{
    public function __construct(private ProxyResponseFieldCatalog $responseFields)
    {
    }

    public function validate(DirectoryData $data): void
    {
        $this->validateConfiguration(
            sourceType: $data->sourceType,
            projectId: $data->projectId,
            matchBy: $data->matchBy,
            apiConfig: $data->apiConfig,
        );
    }

    public function validateDirectory(Directory $directory): void
    {
        $this->validateConfiguration(
            sourceType: (string)$directory->source_type,
            projectId: (string)$directory->project_id,
            matchBy: $directory->match_by,
            apiConfig: $directory->api_config_json,
        );
    }

    /**
     * @param array<array-key, mixed>|null $apiConfig
     */
    private function validateConfiguration(
        string $sourceType,
        string $projectId,
        ?string $matchBy,
        ?array $apiConfig,
    ): void {
        if ($sourceType !== 'api') {
            return;
        }

        $mapping = $apiConfig['field_mapping'] ?? null;
        $configured = $apiConfig['external_key_field'] ?? null;
        $legacy = $matchBy !== null && is_array($mapping) ? ($mapping[$matchBy] ?? null) : null;
        $proxyField = is_string($configured) && $configured !== '' ? $configured : $legacy;

        if (!is_string($proxyField) || $proxyField === '') {
            return;
        }

        $proxyUuid = $apiConfig['proxy_uuid'] ?? null;
        $endpoint = is_string($proxyUuid)
            ? ProxyEndpoint::query()
                ->where('uuid', $proxyUuid)
                ->where('project_id', $projectId)
                ->where('is_active', true)
                ->first()
            : null;

        if (!$endpoint instanceof ProxyEndpoint) {
            $this->fail('Для проверки ключевого поля нужен активный Proxy текущего проекта.');
        }

        if (!in_array($proxyField, $this->responseFields->keys($endpoint), true)) {
            $this->fail("Поле [{$proxyField}] отсутствует среди полей ответа выбранного Proxy.");
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['api_config.external_key_field' => [$message]]);
    }
}
