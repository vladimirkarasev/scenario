<?php

declare(strict_types=1);

namespace Module\Actions\Services;

use Module\Actions\DTO\ActionCredentialData;
use Module\Actions\Models\ActionCredential;
use Module\Actions\Repositories\ActionCredentialRepository;

final readonly class ActionCredentialService
{
    public function __construct(
        private ActionCredentialRepository $credentials,
    ) {}

    /** @return array<int, array<string, mixed>> */
    public function items(): array
    {
        return $this->credentials
            ->orderedByName()
            ->map(fn (ActionCredential $credential): array => $this->payload($credential))
            ->values()
            ->all();
    }

    /** @return array<string, mixed> */
    public function create(ActionCredentialData $data): array
    {
        $this->ensureManageAccess($data->canManageActions);

        $credential = $this->credentials->create([
            'name' => $data->name,
            'type' => $data->type,
            'config' => $data->config,
            'encrypted_secrets' => $data->secrets,
        ]);

        return $this->payload($credential);
    }

    /** @return array<string, mixed> */
    public function update(ActionCredentialData $data, ActionCredential $credential): array
    {
        $this->ensureManageAccess($data->canManageActions);

        $mergedSecrets = [
            ...$credential->encrypted_secrets,
            ...collect($data->secrets)
                ->filter(static fn (mixed $value): bool => $value !== null && $value !== '')
                ->all(),
        ];

        $credential = $this->credentials->update($credential, [
            'name' => $data->name,
            'type' => $data->type,
            'config' => $data->config,
            'encrypted_secrets' => $mergedSecrets,
        ]);

        return $this->payload($credential);
    }

    public function delete(bool $canManageActions, ActionCredential $credential): void
    {
        $this->ensureManageAccess($canManageActions);

        $this->credentials->delete($credential);
    }

    /** @return array<string, mixed> */
    public function payload(ActionCredential $credential): array
    {
        return [
            'id' => $credential->id,
            'name' => $credential->name,
            'type' => $credential->type,
            'config' => $credential->config,
            'masked_secrets' => $credential->maskedSecrets(),
            'has_secrets' => $credential->maskedSecrets() !== [],
            'created_at' => $credential->created_at?->toIso8601String(),
            'updated_at' => $credential->updated_at?->toIso8601String(),
        ];
    }

    private function ensureManageAccess(bool $canManageActions): void
    {
        abort_unless($canManageActions, 403);
    }
}
