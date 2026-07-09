<?php

declare(strict_types=1);

namespace App\Workers;

use Laravel\Octane\ApplicationFactory;
use RoadRunner\Centrifugo\CentrifugoWorker as RRCentrifugoWorker;
use RoadRunner\Centrifugo\Payload\SubscribeResponse;
use RoadRunner\Centrifugo\Request\Connect;
use RoadRunner\Centrifugo\Request\Invalid;
use RoadRunner\Centrifugo\Request\Publish;
use RoadRunner\Centrifugo\Request\RequestFactory;
use RoadRunner\Centrifugo\Request\RequestInterface;
use RoadRunner\Centrifugo\Request\RPC;
use RoadRunner\Centrifugo\Request\Subscribe;
use RoadRunner\Centrifugo\Request\SubRefresh;
use RoadRunner\Centrifugo\Request\Refresh;
use Spiral\RoadRunner\Worker as RoadRunnerWorker;
use Spiral\RoadRunnerLaravel\OctaneWorker;
use Spiral\RoadRunnerLaravel\WorkerInterface;
use Spiral\RoadRunnerLaravel\WorkerOptionsInterface;

/**
 * Handles Centrifugo's subscribe proxy events (RR_MODE=centrifuge, see .rr.yaml `centrifuge:` section
 * and config/roadrunner.php `workers['centrifuge']`). Connect auth is handled by Centrifugo itself
 * (client.token.hmac_secret_key, docker/centrifugo/config.json) — a short-lived JWT minted by
 * App\Http\Controllers\CentrifugoTokenController with `sub` = user id, no connect-proxy round trip
 * (RR's centrifuge plugin can't forward the client-supplied token as a header on any released
 * Centrifugo version — the `emulated_headers` feature that would allow it isn't in a release yet).
 * Centrifugo passes the JWT's `sub` through as `$request->user` on every subsequent proxy event, so
 * subscribe here just needs to check it's non-empty.
 *
 * Each event runs through Laravel\Octane\Worker::handleTask(), the same per-task container
 * sandboxing the bridge's own QueueWorker uses for jobs — a fresh cloned app per event, so nothing
 * resolved while handling one WebSocket connection leaks into another's.
 */
final class CentrifugoWorker implements WorkerInterface
{
    public function start(WorkerOptionsInterface $options): void
    {
        $octaneWorker = new OctaneWorker(new ApplicationFactory($options->getAppBasePath()));
        $octaneWorker->boot();

        $rrWorker = RoadRunnerWorker::create();
        $centrifugoWorker = new RRCentrifugoWorker($rrWorker, new RequestFactory($rrWorker));

        while ($request = $centrifugoWorker->waitRequest()) {
            if ($request instanceof Invalid) {
                report($request->getException());

                continue;
            }

            $octaneWorker->handleTask(function () use ($request): void {
                $this->handle($request);
            });
        }
    }

    private function handle(RequestInterface $request): void
    {
        try {
            match (true) {
                $request instanceof Subscribe => $this->handleSubscribe($request),
                $request instanceof Connect, $request instanceof Refresh, $request instanceof SubRefresh,
                $request instanceof RPC, $request instanceof Publish
                    => $request->error(404, 'Not implemented'),
                default => null,
            };
        } catch (\Throwable $e) {
            report($e);
            $request->error(500, 'Internal error');
        }
    }

    private function handleSubscribe(Subscribe $request): void
    {
        if ($request->user === '') {
            $request->error(403, 'Unauthorized');

            return;
        }

        $request->respond(new SubscribeResponse());
    }
}
