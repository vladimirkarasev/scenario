<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\DaData;

use DateTimeImmutable;
use DateTimeInterface;
use Module\Proxy\Gateway\Base\DTO\ApiGatewayConfig;
use Module\Proxy\Gateway\Base\Methods\GetJsonMethod;
use Module\Proxy\Gateway\Base\Services\BaseApiGateway;
use Module\Proxy\Gateway\Base\Transports\GuzzleApiTransport;
use Module\Proxy\Gateway\Base\Transports\MockApiTransport;

final class DaDataProfileGateway extends BaseApiGateway
{
    public function __construct(
        ApiGatewayConfig $config,
        GuzzleApiTransport $transport,
        MockApiTransport $mockTransport,
    ) {
        parent::__construct(
            config: $config,
            transport: $transport,
            mockTransport: $mockTransport,
        );
    }

    /** @throws \Throwable */
    public function getBalance(): float
    {
        $response = $this->send(new GetJsonMethod('profile/balance', key: 'dadata.profile.balance'));
        $balance = $response->json('balance');

        return is_numeric($balance) ? (float) $balance : 0.0;
    }

    /**
     * @return array<string, mixed>
     * @throws \Throwable
     */
    public function getDailyStats(?DateTimeInterface $date = null): array
    {
        $date ??= new DateTimeImmutable;
        $response = $this->send(new GetJsonMethod(
            'stat/daily',
            ['date' => $date->format('Y-m-d')],
            key: 'dadata.profile.stat_daily',
        ));

        return is_array($response->body) ? $this->toStringKeyed($response->body) : [];
    }

    /**
     * @return array<string, mixed>
     * @throws \Throwable
     */
    public function getVersions(): array
    {
        $response = $this->send(new GetJsonMethod('version', key: 'dadata.profile.versions'));

        return is_array($response->body) ? $this->toStringKeyed($response->body) : [];
    }

    /**
     * @param  array<mixed, mixed>  $array
     * @return array<string, mixed>
     */
    private function toStringKeyed(array $array): array
    {
        $result = [];
        foreach ($array as $key => $value) {
            $result[(string) $key] = $value;
        }

        return $result;
    }
}
