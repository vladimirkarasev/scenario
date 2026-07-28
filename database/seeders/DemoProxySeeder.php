<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Module\Projects\Models\Project;
use Module\Proxy\Credentials\AutoCrm\AutoCrmCredential;
use Module\Proxy\Enums\ProxyEndpointType;
use Module\Proxy\Models\ProxyConnection;
use Module\Proxy\Models\ProxyEndpoint;
use Module\Proxy\Proxies\AutoCrm\BrandsProxyHandler;
use Module\Proxy\Proxies\AutoCrm\DealersProxyHandler;
use Module\Proxy\Proxies\AutoCrm\ModelsProxyHandler;

/**
 * Demo-интеграции с разделами и мок-ответами. Идемпотентен: разделы и эндпоинты
 * привязаны к фиксированным UUID, повторный запуск обновляет, а не дублирует.
 *
 * Все эндпоинты создаются в режиме мока (`is_mocked = true`) — их можно дёргать
 * локально без реальных внешних сервисов.
 */
final class DemoProxySeeder extends Seeder
{
    // Разделы (фиксированные UUID, чтобы эндпоинты могли на них ссылаться).
    private const string SECTION_AUTOCRM = '019e6100-0000-7000-8000-000000000001';
    private const string SECTION_AUTOCRM_CATALOG = '019e6100-0000-7000-8000-000000000002';

    private ?string $projectId = null;

    public function run(): void
    {
        // Привязываем demo-интеграции к demo-проекту (под ним работает admin-пользователь).
        $projectId = Project::query()
            ->where('sitekey', DemoProjectSeeder::SITEKEY)
            ->value('id');
        $this->projectId = is_string($projectId) ? $projectId : null;

        $autocrm = $this->section(self::SECTION_AUTOCRM, 'AutoCRM', null);
        $catalog = $this->section(self::SECTION_AUTOCRM_CATALOG, 'Каталог', self::SECTION_AUTOCRM);

        // Общий доступ для всех AutoCRM-эндпоинтов (URL + токен в одном месте).
        $autocrmConn = $this->connection('AutoCRM (demo)', AutoCrmCredential::class, [
            'base_uri' => 'https://crm.example.com/api/v1',
        ], [
            'bearer_token' => 'demo-autocrm-token',
        ]);

        // ── AutoCRM / Каталог ────────────────────────────────────────────────
        $this->endpoint(
            uuid: '019e6100-1000-7000-8000-000000000001',
            code: 'autocrm-models',
            name: 'Список моделей',
            handler: ModelsProxyHandler::class,
            sections: [$catalog->id],
            mocks: [
                $this->mock('Каталог моделей', 200, [
                    'request_id' => 'mock-models',
                    'items' => [
                        ['id' => 101, 'name' => 'Vesta', 'alias_id' => 11, 'brand_id' => 1],
                        ['id' => 102, 'name' => 'Granta', 'alias_id' => 12, 'brand_id' => 1],
                        ['id' => 201, 'name' => 'Camry', 'alias_id' => 21, 'brand_id' => 2],
                    ],
                ]),
            ],
            connectionId: $autocrmConn->id,
        );

        $this->endpoint(
            uuid: '019e6100-1000-7000-8000-000000000002',
            code: 'autocrm-brands',
            name: 'Список брендов',
            handler: BrandsProxyHandler::class,
            sections: [$catalog->id],
            mocks: [
                $this->mock('Каталог брендов', 200, [
                    'request_id' => 'mock-brands',
                    'items' => [
                        ['id' => 1, 'name' => 'LADA'],
                        ['id' => 2, 'name' => 'Toyota'],
                        ['id' => 3, 'name' => 'Kia'],
                    ],
                ]),
            ],
            connectionId: $autocrmConn->id,
        );

        $this->endpoint(
            uuid: '019e6100-1000-7000-8000-000000000003',
            code: 'autocrm-dealers',
            name: 'Список дилеров',
            handler: DealersProxyHandler::class,
            sections: [$catalog->id],
            mocks: [
                $this->mock('Каталог дилеров', 200, [
                    'request_id' => 'mock-dealers',
                    'items' => [
                        ['id' => 1, 'name' => 'Автоцентр Север', 'city' => 'Москва'],
                        ['id' => 2, 'name' => 'Автоцентр Юг', 'city' => 'Краснодар'],
                    ],
                ]),
            ],
            connectionId: $autocrmConn->id,
        );
    }

    /**
     * Создаёт/обновляет раздел и линкует его к model_type ProxyEndpoint
     * (self-link в model_has_categories — иначе раздел не виден в списке интеграций).
     */
    private function section(string $id, string $name, ?string $parentId): Category
    {
        $category = Category::query()->updateOrCreate(
            ['id' => $id],
            ['name' => $name, 'parent_id' => $parentId, 'is_active' => true],
        );

        DB::table('model_has_categories')->updateOrInsert(
            [
                'category_id' => $category->id,
                'model_id' => $category->id,
                'model_type' => ProxyEndpoint::class,
            ],
            ['project_id' => $this->projectId, 'created_at' => now(), 'updated_at' => now()],
        );

        return $category;
    }

    /**
     * Создаёт/обновляет доступ (connection). Идемпотентно по (project_id, name, credential_type).
     *
     * @param  class-string<\Module\Proxy\Credentials\ProxyCredential>  $type
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $secrets
     */
    private function connection(string $name, string $type, array $config, array $secrets): ProxyConnection
    {
        return ProxyConnection::query()->updateOrCreate(
            ['project_id' => $this->projectId, 'name' => $name, 'credential_type' => $type],
            ['config' => $config, 'secrets' => $secrets],
        );
    }

    /**
     * @param  class-string  $handler
     * @param  list<string>  $sections  id разделов
     * @param  list<array<string, mixed>>  $mocks
     */
    private function endpoint(
        string $uuid,
        string $code,
        string $name,
        string $handler,
        array $sections,
        array $mocks,
        ?int $connectionId = null,
        ProxyEndpointType $type = ProxyEndpointType::Webhook,
    ): void {
        $endpoint = ProxyEndpoint::query()->updateOrCreate(
            ['code' => $code],
            [
                'project_id' => $this->projectId,
                'uuid' => $uuid,
                'name' => $name,
                'type' => $type->value,
                'description' => 'Demo-интеграция (мок)',
                'is_active' => true,
                'is_mocked' => true,
                'handler_class' => $handler,
                'connection_id' => $connectionId,
                'method' => 'POST',
                'config' => [],
                'mock_responses' => $mocks,
            ],
        );

        $pivot = [];
        foreach ($sections as $sectionId) {
            $pivot[$sectionId] = ['project_id' => $this->projectId];
        }
        $endpoint->categories()->sync($pivot);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function mock(string $name, int $status, array $body, bool $active = false): array
    {
        return [
            'name' => $name,
            'status' => $status,
            'body' => $body,
            'headers' => null,
            'is_active' => $active,
        ];
    }
}
