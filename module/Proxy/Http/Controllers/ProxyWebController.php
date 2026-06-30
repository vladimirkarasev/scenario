<?php

declare(strict_types=1);

namespace Module\Proxy\Http\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

final class ProxyWebController extends Controller
{
    public function proxies(): Response
    {
        return Inertia::render('Proxy/Proxies');
    }

    public function connections(): Response
    {
        return Inertia::render('Proxy/Connections');
    }

    public function proxyRequests(): Response
    {
        return Inertia::render('Proxy/ProxyRequests');
    }

    public function proxyRequestDetail(string $proxyRequest): Response
    {
        return Inertia::render('Proxy/ProxyRequestDetail', [
            'requestLogId' => $proxyRequest,
        ]);
    }
}
