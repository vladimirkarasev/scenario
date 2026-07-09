<?php

declare(strict_types=1);

namespace Module\Proxy\Models;

use App\Models\Category;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Module\Projects\Models\Project;
use Module\Proxy\Enums\ProxyEndpointType;

/**
 * @property int $id
 * @property string|null $project_id
 * @property string $uuid
 * @property string $name
 * @property string $code
 * @property ProxyEndpointType $type
 * @property string|null $description
 * @property bool $is_active
 * @property bool $is_mocked
 * @property string $handler_class
 * @property int|null $connection_id
 * @property string|null $method
 * @property string|null $base_uri
 * @property array<string, mixed>|null $credentials
 * @property array<string, mixed>|null $config
 * @property array<int, array<string, mixed>>|null $mock_responses
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Category> $categories
 */
final class ProxyEndpoint extends Model
{
    protected $table = 'proxy_endpoints';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'webhook',
        'is_active' => true,
        'is_mocked' => false,
    ];

    protected $fillable = [
        'project_id',
        'uuid',
        'name',
        'code',
        'type',
        'description',
        'is_active',
        'is_mocked',
        'handler_class',
        'connection_id',
        'method',
        'base_uri',
        'credentials',
        'config',
        'mock_responses',
        'created_by',
    ];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<ProxyConnection, $this> */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(ProxyConnection::class);
    }

    /** @return HasMany<ProxyRequest, $this> */
    public function requests(): HasMany
    {
        return $this->hasMany(ProxyRequest::class);
    }

    /**
     * @return MorphToMany<Category, $this>
     */
    public function categories(): MorphToMany
    {
        return $this->morphToMany(
            Category::class,
            'model',
            'model_has_categories',
            'model_id',
            'category_id',
            'uuid',
            'id'
        )
            ->withPivot('project_id')
            ->withTimestamps();
    }

    #[\Override]
    protected function casts(): array
    {
        return [
            'type' => ProxyEndpointType::class,
            'is_active' => 'boolean',
            'is_mocked' => 'boolean',
            'credentials' => 'encrypted:array',
            'config' => 'array',
            'mock_responses' => 'array',
        ];
    }
}
