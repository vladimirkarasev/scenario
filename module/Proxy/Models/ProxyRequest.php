<?php

declare(strict_types=1);

namespace Module\Proxy\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
use Module\Proxy\Enums\ProxyRequestStatus;
use Module\Proxy\QueryBuilders\ProxyRequestBuilder;

/**
 * @property string                    $id
 * @property string                    $proxy_endpoint_id
 * @property string                    $request_id
 * @property ProxyRequestStatus        $status
 * @property bool                      $is_mocked
 * @property array<string, mixed>|null $request
 * @property array<string, mixed>|null $normalized_data
 * @property array<string, mixed>|null $message_box
 * @property array<string, mixed>|null $response
 * @property Carbon|null               $received_at
 * @property Carbon|null               $processed_at
 * @property ProxyEndpoint|null        $endpoint
 *
 * @method static ProxyRequestBuilder query()
 */
final class ProxyRequest extends Model
{
    use HasUuids;

    protected $table = 'proxy_requests';

    protected $fillable = [
        'proxy_endpoint_id',
        'request_id',
        'status',
        'is_mocked',
        'request',
        'normalized_data',
        'message_box',
        'response',
        'received_at',
        'processed_at',
    ];

    /** @return BelongsTo<ProxyEndpoint, $this> */
    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(ProxyEndpoint::class, 'proxy_endpoint_id');
    }

    /** @param  Builder  $query */
    #[\Override]
    public function newEloquentBuilder($query): ProxyRequestBuilder
    {
        return new ProxyRequestBuilder($query);
    }

    #[\Override]
    protected function casts(): array
    {
        return [
            'request' => 'array',
            'normalized_data' => 'array',
            'message_box' => 'array',
            'response' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
            'status' => ProxyRequestStatus::class,
            'is_mocked' => 'boolean',
        ];
    }
}
