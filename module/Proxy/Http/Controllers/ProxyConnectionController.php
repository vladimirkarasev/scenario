<?php

declare(strict_types=1);

namespace Module\Proxy\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Module\Projects\CurrentProject;
use Module\Proxy\Http\Resources\JsonApi\ProxyConnectionResource;
use Module\Proxy\Models\ProxyConnection;
use Module\Proxy\Services\CredentialCatalog;

/**
 * CRUD доступов (connection): переиспользуемая запись «куда + как авторизуемся».
 * Тип доступа (`credential_type`) — драйвер из {@see CredentialCatalog}; форма полей
 * рендерится по его схеме, секреты шифруются и маскируются. Скоуп — по текущему проекту.
 */
final class ProxyConnectionController extends Controller
{
    public function __construct(private readonly CurrentProject $currentProject) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $filter = $request->array('filter');
        $type = isset($filter['credential_type']) && is_string($filter['credential_type']) && $filter['credential_type'] !== ''
            ? $filter['credential_type']
            : null;

        $perPage = max(1, min(100, $request->integer('page.size', 100)));

        $query = ProxyConnection::query()->latest();

        $projectId = $this->currentProject->id();
        if ($projectId !== null) {
            $query->where('project_id', $projectId);
        }

        if ($type !== null) {
            $query->where('credential_type', $type);
        }

        return ProxyConnectionResource::collection($query->paginate($perPage, ['*'], 'page[number]'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $type = is_string($data['credential_type'] ?? null) ? $data['credential_type'] : '';
        $name = is_string($data['name'] ?? null) ? $data['name'] : '';

        $connection = new ProxyConnection([
            'project_id' => $this->currentProject->id(),
            'name' => $name,
            'credential_type' => $type,
        ]);

        $this->applyValues($connection, $type, $this->incomingValues($data));
        $connection->save();

        return (new ProxyConnectionResource($connection))->response()->setStatusCode(201);
    }

    public function show(ProxyConnection $connection): ProxyConnectionResource
    {
        return new ProxyConnectionResource($connection);
    }

    public function update(Request $request, ProxyConnection $connection): ProxyConnectionResource
    {
        $data = $this->validated($request);

        $type = is_string($data['credential_type'] ?? null) ? $data['credential_type'] : '';
        $name = is_string($data['name'] ?? null) ? $data['name'] : '';

        $connection->name = $name;
        $connection->credential_type = $type;
        $this->applyValues($connection, $type, $this->incomingValues($data));
        $connection->save();

        return new ProxyConnectionResource($connection);
    }

    public function destroy(ProxyConnection $connection): JsonResponse
    {
        $connection->delete();

        return new JsonResponse(status: 204);
    }

    /** Каталог типов доступа со схемой полей — для формы и пикера в UI. */
    public function types(): JsonResponse
    {
        return new JsonResponse(['data' => CredentialCatalog::options()]);
    }

    /**
     * Раскладывает входящие значения по config (несекретное) и secrets (шифрованное)
     * согласно полям драйвера. Пустой секрет при update = «не менять» (берём сохранённый).
     *
     * @param  array<string, mixed>  $values
     */
    private function applyValues(ProxyConnection $connection, string $type, array $values): void
    {
        $driver = CredentialCatalog::make($type);
        $secretKeys = array_flip($driver->secretKeys());
        $existingSecrets = $connection->secrets ?? [];

        $config = [];
        $secrets = [];

        foreach ($driver->fieldKeys() as $key) {
            $value = $values[$key] ?? null;

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

        $connection->config = $config === [] ? null : $config;
        $connection->secrets = $secrets === [] ? null : $secrets;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function incomingValues(array $data): array
    {
        $values = $data['values'] ?? [];

        if (! is_array($values)) {
            return [];
        }

        $result = [];
        foreach ($values as $key => $value) {
            $result[(string) $key] = $value;
        }

        return $result;
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        /** @var array<string, mixed> */
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'credential_type' => ['required', 'string', Rule::in(CredentialCatalog::all())],
            'values' => ['nullable', 'array'],
        ]);
    }
}
