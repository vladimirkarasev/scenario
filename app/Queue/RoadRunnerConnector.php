<?php

declare(strict_types=1);

namespace App\Queue;

use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Connectors\ConnectorInterface;
use Spiral\Goridge\RPC\Codec\ProtobufCodec;
use Spiral\Goridge\RPC\RPC;
use Spiral\RoadRunner\Environment;
use Spiral\RoadRunner\Jobs\Jobs;

final class RoadRunnerConnector implements ConnectorInterface
{
    /**
     * @param array<array-key, mixed> $config
     */
    public function connect(array $config): QueueContract
    {
        $env = Environment::fromGlobals();

        $rpcAddress = $env->getRPCAddress();
        if ($rpcAddress === '') {
            throw new \RuntimeException('RoadRunner RPC address (RR_RPC env) must not be empty.');
        }

        $rpc = RPC::create($rpcAddress)->withCodec(new ProtobufCodec());

        $queue = $config['queue'] ?? 'default';
        if (!is_string($queue) || $queue === '') {
            throw new \InvalidArgumentException('queue.connections.roadrunner.queue config must be a non-empty string.');
        }

        $options = $config['options'] ?? [];
        if (!is_array($options)) {
            throw new \InvalidArgumentException('queue.connections.roadrunner.options config must be an array.');
        }

        return new RoadRunnerQueue(
            new Jobs($rpc),
            $rpc,
            $queue,
            $options,
        );
    }
}
