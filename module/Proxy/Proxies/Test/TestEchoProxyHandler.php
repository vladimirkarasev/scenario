<?php

declare(strict_types=1);

namespace Module\Proxy\Proxies\Test;

use Module\Proxy\DTO\ProxyContext;
use Module\Proxy\DTO\ProxyFieldArrayList;
use Module\Proxy\DTO\ProxyResponse;
use Module\Proxy\ProxyHandler;

final class TestEchoProxyHandler extends ProxyHandler
{
    public function fields(): iterable
    {
        yield ProxyFieldArrayList::make('items')
            ->label('Элементы справочника')
            ->nullable()
            ->rules(['nullable', 'array'])
            ->example([
                ['id' => 1, 'name' => 'Первый элемент', 'code' => 'first'],
                ['id' => 2, 'name' => 'Второй элемент', 'code' => 'second'],
            ]);
    }

    #[\Override]
    public function handle(ProxyContext $proxyContext): ProxyResponse
    {
        $items = $proxyContext->data('items');

        return ProxyResponse::ok([
            'request_id' => $proxyContext->requestId(),
            'items' => is_array($items) ? $items : [],
            'meta' => [
                'endpoint' => $proxyContext->endpoint->code,
                'received_at' => now()->toIso8601String(),
                'count' => is_array($items) ? count($items) : 0,
            ],
        ]);
    }
}
