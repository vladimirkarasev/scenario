<?php

declare(strict_types=1);

namespace Module\Proxy\Listeners;

use Module\Proxy\Events\ProxyRequestAccepted;
use Module\Proxy\Events\ProxyRequestFailed;
use Module\Proxy\Events\ProxyRequestProcessed;
use Module\Proxy\Events\ProxyRequestRejected;
use Module\Proxy\Models\ProxyRequest;

final class PersistProxyContext
{
    public function handleAccepted(ProxyRequestAccepted $event): void
    {
        $this->save($event->proxyRequest, $event->context->toArray());
    }

    public function handleProcessed(ProxyRequestProcessed $event): void
    {
        $this->save($event->proxyRequest, $event->context->toArray());
    }

    public function handleRejected(ProxyRequestRejected $event): void
    {
        $this->save($event->proxyRequest, $event->context->toArray());
    }

    public function handleFailed(ProxyRequestFailed $event): void
    {
        $this->save($event->proxyRequest, $event->context->toArray());
    }

    /** @param  array<string, mixed>  $snapshot */
    private function save(ProxyRequest $proxyRequest, array $snapshot): void
    {
        $proxyRequest->forceFill(['message_box' => $snapshot])->save();
    }
}
