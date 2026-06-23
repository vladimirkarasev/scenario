<?php

declare(strict_types=1);

namespace Module\Actions\Services;

use Module\Actions\Enums\ActionCredentialType;
use Module\Actions\Models\ActionCredential;
use Module\Actions\Repositories\ActionCredentialRepository;

final class ActionCredentialResolver
{
    public function __construct(
        private readonly ActionCredentialRepository $credentials,
    ) {
    }

    /** @return array{headers: array<string, string>, query: array<string, string>} */
    public function resolve(?int $credentialId): array
    {
        if ($credentialId === null) {
            return [
                'headers' => [],
                'query' => [],
            ];
        }

        $credential = $this->credentials->find($credentialId);

        if ($credential === null) {
            return [
                'headers' => [],
                'query' => [],
            ];
        }

        $secrets = $credential->encrypted_secrets;

        return match (ActionCredentialType::from($credential->type)) {
            ActionCredentialType::None => ['headers' => [], 'query' => []],
            ActionCredentialType::Bearer => [
                'headers' => [
                    'Authorization' => 'Bearer '.($secrets['token'] ?? ''),
                ],
                'query' => [],
            ],
            ActionCredentialType::Basic => [
                'headers' => [
                    'Authorization' => 'Basic '.base64_encode(
                            $this->stringConfig($credential, 'username').':'.($secrets['password'] ?? ''),
                        ),
                ],
                'query' => [],
            ],
            ActionCredentialType::ApiKey => $this->resolveApiKey($credential),
            ActionCredentialType::Oauth2Placeholder => [
                'headers' => [
                    'Authorization' => 'Bearer '.($secrets['access_token'] ?? ''),
                ],
                'query' => [],
            ],
        };
    }

    /** @return array{headers: array<string, string>, query: array<string, string>} */
    private function resolveApiKey(ActionCredential $credential): array
    {
        $location = $this->stringConfig($credential, 'location', 'header');
        $key = $this->stringConfig($credential, 'key', 'X-API-Key');
        $value = $credential->encrypted_secrets['value'] ?? '';

        if ($location === 'query') {
            return [
                'headers' => [],
                'query' => [$key => $value],
            ];
        }

        return [
            'headers' => [$key => $value],
            'query' => [],
        ];
    }

    private function stringConfig(ActionCredential $credential, string $key, string $default = ''): string
    {
        $value = $credential->config[$key] ?? null;

        return is_string($value) ? $value : $default;
    }
}
