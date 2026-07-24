<?php

declare(strict_types=1);

namespace Module\Proxy\Services;

use App\Exceptions\NotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Module\Projects\CurrentProject;
use Module\Proxy\DTO\ProxyEndpointData;
use Module\Proxy\DTO\ProxyEndpointIndexData;
use Module\Proxy\Enums\ProxyErrorCode;
use Module\Proxy\Models\ProxyConnection;
use Module\Proxy\Models\ProxyEndpoint;
use Module\Proxy\Repositories\ProxyEndpointRepository;
use Module\Proxy\Support\IntegrationCredentials;

final readonly class ProxyEndpointService
{
    public function __construct(
        private ProxyEndpointRepository $endpoints,
        private HandlerResolver $handlers,
        private CurrentProject $currentProject,
    ) {}

    /** @return LengthAwarePaginator<int, ProxyEndpoint> */
    public function paginate(ProxyEndpointIndexData $data): LengthAwarePaginator
    {
        return $this->endpoints->paginate($data, $this->projectId());
    }

    public function find(ProxyEndpoint $endpoint): ProxyEndpoint
    {
        $this->assertInCurrentProject($endpoint);

        return $endpoint->load(['categories', 'connection']);
    }

    public function create(ProxyEndpointData $data, ?int $actorId): ProxyEndpoint
    {
        return DB::transaction(function () use ($data, $actorId): ProxyEndpoint {
            $connectionId = $this->resolveConnectionId($data);
            $credentials = $this->credentials($data->credentials);

            $endpoint = $this->endpoints->create([
                'project_id' => $this->projectId(),
                'uuid' => (string) Str::uuid(),
                'name' => $data->name,
                'code' => $data->code,
                'type' => $data->type,
                'description' => $data->description,
                'is_active' => $data->isActive,
                'is_mocked' => $data->isMocked,
                'handler_class' => $data->handlerClass,
                'connection_id' => $connectionId,
                'method' => $this->resolveMethod($data),
                'base_uri' => $credentials['base_uri'],
                'credentials' => $credentials['credentials'],
                'config' => $data->config,
                'mock_responses' => $data->mockResponses,
                'created_by' => $actorId,
            ]);

            $this->syncCategories($endpoint, $data->categoryIds);

            return $endpoint->load(['categories', 'connection']);
        });
    }

    public function update(ProxyEndpointData $data, ProxyEndpoint $endpoint): ProxyEndpoint
    {
        $this->assertInCurrentProject($endpoint);

        return DB::transaction(function () use ($data, $endpoint): ProxyEndpoint {
            $connectionId = $this->resolveConnectionId($data);
            $credentials = $this->credentials($data->credentials, $endpoint->credentials ?? []);

            $endpoint = $this->endpoints->update($endpoint, [
                'name' => $data->name,
                'code' => $data->code,
                'type' => $data->type,
                'description' => $data->description,
                'is_active' => $data->isActive,
                'is_mocked' => $data->isMocked,
                'handler_class' => $data->handlerClass,
                'connection_id' => $connectionId,
                'method' => $this->resolveMethod($data),
                'base_uri' => $credentials['base_uri'],
                'credentials' => $credentials['credentials'],
                'config' => $data->config,
                'mock_responses' => $data->mockResponses,
            ]);

            if ($data->categoriesProvided) {
                $this->syncCategories($endpoint, $data->categoryIds);
            }

            return $endpoint->load(['categories', 'connection']);
        });
    }

    public function delete(ProxyEndpoint $endpoint): void
    {
        $this->assertInCurrentProject($endpoint);
        $this->endpoints->delete($endpoint);
    }

    private function resolveMethod(ProxyEndpointData $data): string
    {
        return $this->handlers->resolveClass($data->handlerClass)->method();
    }

    private function resolveConnectionId(ProxyEndpointData $data): ?int
    {
        $required = $this->handlers->resolveClass($data->handlerClass)->credentialType();
        if ($data->connectionId === null) {
            return null;
        }
        if ($required === null) {
            throw ValidationException::withMessages([
                'connection_id' => 'Этот обработчик не использует доступы.',
            ]);
        }

        $connection = ProxyConnection::query()
            ->where('project_id', $this->projectId())
            ->find($data->connectionId);
        if ($connection === null || $connection->credential_type !== $required) {
            throw ValidationException::withMessages([
                'connection_id' => 'Доступ не подходит выбранному обработчику.',
            ]);
        }

        return $connection->id;
    }

    /**
     * @param array<string, mixed> $incoming
     * @param array<string, mixed> $existing
     * @return array{base_uri: string|null, credentials: array<string, mixed>|null}
     */
    private function credentials(array $incoming, array $existing = []): array
    {
        $baseUri = $incoming['base_uri'] ?? null;
        unset($incoming['base_uri']);

        foreach (IntegrationCredentials::secretKeys() as $key) {
            if (!filled($incoming[$key] ?? null) && array_key_exists($key, $existing)) {
                $incoming[$key] = $existing[$key];
            }
        }

        return [
            'base_uri' => is_scalar($baseUri) && (string) $baseUri !== '' ? (string) $baseUri : null,
            'credentials' => $incoming === [] ? null : $incoming,
        ];
    }

    /** @param list<string> $categoryIds */
    private function syncCategories(ProxyEndpoint $endpoint, array $categoryIds): void
    {
        if ($categoryIds === []) {
            $endpoint->categories()->sync([]);

            return;
        }

        $validIds = DB::table('model_has_categories')
            ->where('model_type', ProxyEndpoint::class)
            ->where('project_id', $this->projectId())
            ->whereIn('category_id', $categoryIds)
            ->distinct()
            ->pluck('category_id')
            ->filter(static fn (mixed $id): bool => is_string($id))
            ->values()
            ->all();

        if (count($validIds) !== count(array_unique($categoryIds))) {
            throw ValidationException::withMessages([
                'category_ids' => 'Один или несколько разделов не принадлежат текущему проекту.',
            ]);
        }

        $pivot = [];
        foreach ($categoryIds as $categoryId) {
            $pivot[$categoryId] = ['project_id' => $this->projectId()];
        }
        $endpoint->categories()->sync($pivot);
    }

    private function assertInCurrentProject(ProxyEndpoint $endpoint): void
    {
        if ($endpoint->project_id !== $this->projectId()) {
            throw NotFoundException::from(ProxyErrorCode::EndpointNotFound);
        }
    }

    private function projectId(): string
    {
        return $this->currentProject->id()
            ?? throw new \LogicException('Proxy endpoint operations require a current project.');
    }
}
