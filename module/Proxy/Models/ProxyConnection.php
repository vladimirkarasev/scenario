<?php

declare(strict_types=1);

namespace Module\Proxy\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Module\Projects\Models\Project;
use Module\Proxy\Credentials\ProxyCredential;
use Module\Proxy\Services\CredentialCatalog;

/**
 * @property int                       $id
 * @property string|null               $project_id
 * @property string                    $name
 * @property string                    $credential_type
 * @property array<string, mixed>|null $config
 * @property array<string, mixed>|null $secrets
 */
final class ProxyConnection extends Model
{
    protected $table = 'proxy_connections';

    protected $fillable = [
        'project_id',
        'name',
        'credential_type',
        'config',
        'secrets',
    ];

    protected $hidden = [
        'secrets',
    ];

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return HasMany<ProxyEndpoint, $this> */
    public function endpoints(): HasMany
    {
        return $this->hasMany(ProxyEndpoint::class, 'connection_id');
    }

    public function driver(): ProxyCredential
    {
        return CredentialCatalog::make($this->credential_type);
    }

    /**
     * @return array<string, mixed>
     */
    public function values(): array
    {
        return array_merge($this->config ?? [], $this->secrets ?? []);
    }

    /** @return array<string, string> */
    #[\Override]
    protected function casts(): array
    {
        return [
            'config' => 'array',
            'secrets' => 'encrypted:array',
        ];
    }
}
