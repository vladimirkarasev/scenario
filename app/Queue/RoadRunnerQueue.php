<?php

declare(strict_types=1);

namespace App\Queue;

use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Queue\Queue as QueueBase;
use Illuminate\Support\Carbon;
use Ramsey\Uuid\Uuid;
use RoadRunner\Jobs\DTO\V1\Stat;
use RoadRunner\Jobs\DTO\V1\Stats;
use Spiral\Goridge\RPC\RPCInterface;
use Spiral\RoadRunner\Jobs\Jobs;
use Spiral\RoadRunner\Jobs\Options;
use Spiral\RoadRunner\Jobs\OptionsInterface;
use Spiral\RoadRunner\Jobs\QueueInterface;
use Spiral\RoadRunnerLaravel\Queue\Contract\HasQueueOptions;

/**
 * Re-implementation of Spiral\RoadRunnerLaravel\Queue\RoadRunnerQueue (roadrunner-php/laravel-bridge
 * 6.5.0), which is declared `final` and doesn't implement Queue::pendingSize()/delayedSize()/
 * reservedSize()/creationTimeOfOldestPendingJob() — added to the contract after the bridge's last
 * release, which makes the bridge's class fatal to even autoload on this Laravel version.
 */
final class RoadRunnerQueue extends QueueBase implements QueueContract
{
    /**
     * @param array<array-key, mixed> $defaultOptions
     */
    public function __construct(
        private readonly Jobs $jobs,
        private readonly RPCInterface $rpc,
        private readonly string $default = 'default',
        private readonly array $defaultOptions = [],
    ) {
    }

    /**
     * @param \Closure|string|object $job
     * @param mixed $data
     * @param string|null $queue
     */
    public function push($job, $data = '', $queue = null)
    {
        return $this->enqueueUsing(
            $job,
            $this->createPayload($job, $queue ?? $this->default, $data),
            $queue,
            null,
            fn(string $payload, ?string $queue): string => $this->pushRaw($payload, $queue, $this->getJobOverrideOptions($job)),
        );
    }

    /**
     * @param string $payload
     * @param string|null $queue
     * @param array<array-key, mixed> $options
     */
    public function pushRaw($payload, $queue = null, array $options = []): string
    {
        $connectedQueue = $this->getQueue($queue, $options);
        $task = $connectedQueue->create(Uuid::uuid4()->toString(), $payload);

        return $connectedQueue->dispatch($task)->getId();
    }

    /**
     * @param \Closure|string|object $job
     * @param mixed $data
     * @param string|null $queue
     */
    public function later($delay, $job, $data = '', $queue = null)
    {
        return $this->enqueueUsing(
            $job,
            $this->createPayload($job, $queue ?? $this->default, $data),
            $queue,
            $delay,
            fn(string $payload, ?string $queue): string => $this->laterRaw($delay, $payload, $queue, $this->getJobOverrideOptions($job)),
        );
    }

    public function pop($queue = null): never
    {
        throw new \BadMethodCallException('Pop is not supported');
    }

    public function size($queue = null): int
    {
        $stat = $this->stat($queue);

        return (int) $stat->getActive() + (int) $stat->getDelayed();
    }

    public function pendingSize($queue = null): int
    {
        return (int) $this->stat($queue)->getActive();
    }

    public function delayedSize($queue = null): int
    {
        return (int) $this->stat($queue)->getDelayed();
    }

    public function reservedSize($queue = null): int
    {
        return (int) $this->stat($queue)->getReserved();
    }

    public function creationTimeOfOldestPendingJob($queue = null): ?int
    {
        return null;
    }

    /**
     * @return int<0, max>
     */
    protected function availableAt($delay = 0): int
    {
        $delay = $this->parseDateInterval($delay);

        return max(0, $delay instanceof \DateTimeInterface
            ? (int) Carbon::parse($delay)->diffInSeconds()
            : (int) $delay);
    }

    /**
     * @param array<array-key, mixed> $options
     */
    private function getQueue(?string $queue, array $options): QueueInterface
    {
        $name = $queue ?? $this->default;

        if ($name === '') {
            throw new \InvalidArgumentException('RoadRunner queue (pipeline) name must not be empty.');
        }

        $connected = $this->jobs->connect($name, $this->getQueueOptions($options));

        if (!$this->stat($connected->getName())->getReady()) {
            $connected->resume();
        }

        return $connected;
    }

    /**
     * @param array<array-key, mixed> $overrides
     */
    private function getQueueOptions(array $overrides): Options
    {
        $config = array_merge($this->defaultOptions, $overrides);

        $delay = $config['delay'] ?? OptionsInterface::DEFAULT_DELAY;
        $priority = $config['priority'] ?? OptionsInterface::DEFAULT_PRIORITY;
        $autoAck = $config['auto_ack'] ?? OptionsInterface::DEFAULT_AUTO_ACK;

        return new Options(
            is_int($delay) ? max(0, $delay) : OptionsInterface::DEFAULT_DELAY,
            is_int($priority) ? max(0, $priority) : OptionsInterface::DEFAULT_PRIORITY,
            is_bool($autoAck) ? $autoAck : OptionsInterface::DEFAULT_AUTO_ACK,
        );
    }

    private function stat(?string $queue): Stat
    {
        $name = $queue ?? $this->default;

        $response = $this->rpc->call('jobs.Stat', new Stats(), Stats::class);
        assert($response instanceof Stats);

        foreach ($response->getStats() as $stat) {
            /** @var Stat $stat */
            if ($stat->getPipeline() === $name) {
                return $stat;
            }
        }

        return new Stat();
    }

    /**
     * @return array<array-key, mixed>
     */
    private function getJobOverrideOptions(string|object $job): array
    {
        if (is_string($job) && class_exists($job)) {
            $job = app($job);
        }

        if ($job instanceof HasQueueOptions) {
            $options = $job->queueOptions();
            if ($options instanceof Options) {
                return $options->toArray();
            }
        }

        return [];
    }

    /**
     * @param array<array-key, mixed> $options
     */
    private function laterRaw(
        \DateTimeInterface|\DateInterval|int $delay,
        string $payload,
        ?string $queue,
        array $options,
    ): string {
        $connectedQueue = $this->getQueue($queue, $options);

        $task = $connectedQueue->create(
            Uuid::uuid4()->toString(),
            $payload,
            $this->getQueueOptions($options)->withDelay($this->availableAt($delay)),
        );

        return $connectedQueue->dispatch($task)->getId();
    }
}
