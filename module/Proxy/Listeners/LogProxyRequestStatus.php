<?php

declare(strict_types=1);

namespace Module\Proxy\Listeners;

use Module\Proxy\Events\ProxyRequestAccepted;
use Module\Proxy\Events\ProxyRequestFailed;
use Module\Proxy\Events\ProxyRequestProcessed;
use Module\Proxy\Events\ProxyRequestRejected;
use Module\Proxy\Services\ProxyRequestLoggerService;

final readonly class LogProxyRequestStatus
{
    public function __construct(private ProxyRequestLoggerService $logger) {}

    public function handleAccepted(ProxyRequestAccepted $event): void
    {
        $this->logger->markAccepted($event->proxyRequest);
    }

    public function handleProcessed(ProxyRequestProcessed $event): void
    {
        $response = $event->context->response ?? throw new \LogicException(
            'ProxyContext has no response after processing.'
        );
        $this->logger->markProcessed($event->proxyRequest, $response, $event->isMocked);
    }

    public function handleRejected(ProxyRequestRejected $event): void
    {
        $this->logger->markRejected($event->proxyRequest, $event->exception);
    }

    public function handleFailed(ProxyRequestFailed $event): void
    {
        $this->logger->markFailed($event->proxyRequest, $event->exception);
    }
}
