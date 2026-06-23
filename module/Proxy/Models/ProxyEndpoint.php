<?php

declare(strict_types=1);

namespace Module\Proxy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $code
 * @property string|null $description
 * @property bool $is_active
 * @property bool $is_mocked
 * @property string $handler_class
 * @property string|null $method
 * @property array<string, mixed>|null $config
 * @property array<int, array<string, mixed>>|null $mock_responses
 * @property int|null $created_by
 */
final class ProxyEndpoint extends Model
{
    protected $table = 'proxy_endpoints';

    protected $fillable = [
        'uuid',
        'name',
        'code',
        'description',
        'is_active',
        'is_mocked',
        'handler_class',
        'method',
        'config',
        'mock_responses',
        'created_by',
    ];

    /** @return HasMany<ProxyRequest, $this> */
    public function requests(): HasMany
    {
        return $this->hasMany(ProxyRequest::class);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_mocked' => 'boolean',
            'config' => 'array',
            'mock_responses' => 'array',
        ];
    }
}
