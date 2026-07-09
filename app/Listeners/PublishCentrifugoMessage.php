<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\CentrifugoMessagePublished;
use RoadRunner\Centrifugo\CentrifugoApiInterface;

final readonly class PublishCentrifugoMessage
{
    public function __construct(private CentrifugoApiInterface $centrifugo)
    {
    }

    public function handle(CentrifugoMessagePublished $event): void
    {
        $this->centrifugo->publish($event->channel, json_encode($event->payload, JSON_THROW_ON_ERROR));
    }
}
