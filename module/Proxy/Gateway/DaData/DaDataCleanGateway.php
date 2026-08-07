<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\DaData;

use Module\Proxy\Gateway\Base\DTO\ApiGatewayConfig;
use Module\Proxy\Gateway\Base\Services\BaseApiGateway;
use Module\Proxy\Gateway\Base\Transports\GuzzleApiTransport;
use Module\Proxy\Gateway\Base\Transports\MockApiTransport;
use Module\Proxy\Gateway\DaData\DTO\DaDataCleanResult;
use Module\Proxy\Gateway\DaData\Methods\CleanMethod;
use Module\Proxy\Gateway\DaData\Methods\CleanRecordMethod;

final class DaDataCleanGateway extends BaseApiGateway
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
    public function cleanAddress(string $value): DaDataCleanResult
    {
        return $this->clean('address', $value);
    }

    /** @throws \Throwable */
    public function cleanPhone(string $value): DaDataCleanResult
    {
        return $this->clean('phone', $value);
    }

    /** @throws \Throwable */
    public function cleanPassport(string $value): DaDataCleanResult
    {
        return $this->clean('passport', $value);
    }

    /** @throws \Throwable */
    public function cleanName(string $value): DaDataCleanResult
    {
        return $this->clean('name', $value);
    }

    /** @throws \Throwable */
    public function cleanEmail(string $value): DaDataCleanResult
    {
        return $this->clean('email', $value);
    }

    /** @throws \Throwable */
    public function clean(string $type, string $value): DaDataCleanResult
    {
        $response = $this->send(new CleanMethod($type, $value));
        $items = $response->body;
        $first = is_array($items) ? ($items[0] ?? []) : [];

        return DaDataCleanResult::fromArray(is_array($first) ? $first : []);
    }

    /**
     * @param  list<string>  $structure
     * @param  array<string, mixed>  $record
     * @throws \Throwable
     */
    public function cleanRecord(array $structure, array $record): DaDataCleanResult
    {
        $response = $this->send(new CleanRecordMethod($structure, $record));
        $item = $response->json('data.0');

        return DaDataCleanResult::fromArray(is_array($item) ? $item : []);
    }
}
