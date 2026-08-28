<?php

declare(strict_types=1);

namespace Module\Proxy\Services;

use App\Exceptions\NotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Module\Projects\CurrentProject;
use Module\Proxy\DTO\ProxyConnectionData;
use Module\Proxy\DTO\ProxyConnectionIndexData;
use Module\Proxy\Enums\ProxyErrorCode;
use Module\Proxy\Models\ProxyConnection;
use Module\Proxy\Repositories\ProxyConnectionRepository;

final readonly class ProxyConnectionService
{
    public function __construct(
        private ProxyConnectionRepository $connections,
        private CurrentProject $currentProject,
        private CredentialCatalog $credentials,
    ) {}

    /** @return LengthAwarePaginator<int, ProxyConnection> */
    public function paginate(ProxyConnectionIndexData $data): LengthAwarePaginator
    {
        return $this->connections->paginate($data, $this->projectId());
    }

    public function find(ProxyConnection $connection): ProxyConnection
    {
        $this->assertInCurrentProject($connection);

        return $connection;
    }

    public function create(ProxyConnectionData $data): ProxyConnection
    {
        $values = $this->values($data->credentialType, $data->values);

        return $this->connections->create([
            'project_id' => $this->projectId(),
            'name' => $data->name,
            'credential_type' => $data->credentialType,
            'config' => $values['config'],
            'secrets' => $values['secrets'],
        ]);
    }

    public function update(ProxyConnectionData $data, ProxyConnection $connection): ProxyConnection
    {
        $this->assertInCurrentProject($connection);
        $values = $this->values($data->credentialType, $data->values, $connection->secrets ?? []);

        return $this->connections->update($connection, [
            'name' => $data->name,
            'credential_type' => $data->credentialType,
            'config' => $values['config'],
            'secrets' => $values['secrets'],
        ]);
    }

    public function delete(ProxyConnection $connection): void
    {
        $this->assertInCurrentProject($connection);
        $this->connections->delete($connection);
    }

    /**
     * @param  array<string, mixed> $incoming
     * @param  array<string, mixed> $existingSecrets
     * @return array{config: array<string, mixed>|null, secrets: array<string, mixed>|null}
     */
    private function values(string $type, array $incoming, array $existingSecrets = []): array
    {
        $driver = $this->credentials->get($type);
        $secretKeys = array_flip($driver->secretKeys());
        $config = [];
        $secrets = [];

        foreach ($driver->fieldKeys() as $key) {
            $value = $incoming[$key] ?? null;
            if (isset($secretKeys[$key])) {
                if (filled($value)) {
                    $secrets[$key] = $value;
                } elseif (array_key_exists($key, $existingSecrets)) {
                    $secrets[$key] = $existingSecrets[$key];
                }

                continue;
            }

            $config[$key] = $value;
        }

        return [
            'config' => $config === [] ? null : $config,
            'secrets' => $secrets === [] ? null : $secrets,
        ];
    }

    private function assertInCurrentProject(ProxyConnection $connection): void
    {
        if ($connection->project_id !== $this->projectId()) {
            throw NotFoundException::from(ProxyErrorCode::ConnectionNotFound);
        }
    }

    private function projectId(): string
    {
        return $this->currentProject->id()
            ?? throw new \LogicException('Proxy connection operations require a current project.');
    }
}
