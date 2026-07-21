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
