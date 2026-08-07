<?php

declare(strict_types=1);

namespace Tests\Unit\Module\Proxy\Gateway;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use Module\Proxy\Gateway\Base\DTO\ApiGatewayConfig;
use Module\Proxy\Gateway\Base\Exceptions\ApiGatewayTimeoutException;
use Module\Proxy\Gateway\DaData\Methods\SuggestMethod;
use Module\Proxy\Gateway\Base\Transports\GuzzleApiTransport;
use Tests\TestCase;

final class GuzzleApiTransportTest extends TestCase
{
    public function test_curl_timeout_is_converted_to_gateway_timeout_exception(): void
    {
        $request = new Request('POST', 'https://suggestions.example.test/suggest/fio');
        $timeout = new ConnectException(
            'cURL error 28: Connection timed out after 5002 milliseconds',
            $request,
            handlerContext: ['errno' => 28],
        );
        $client = $this->createMock(ClientInterface::class);
        $client->method('request')->willThrowException($timeout);
        $transport = new GuzzleApiTransport($client);

        try {
            $transport->send(
                new ApiGatewayConfig('dadata_suggest', 'https://suggestions.example.test/'),
                new SuggestMethod('fio', 'Иванов'),
            );
            self::fail('A gateway timeout exception was not thrown.');
        } catch (ApiGatewayTimeoutException $exception) {
            self::assertSame('Upstream gateway timed out.', $exception->getMessage());
            self::assertSame($timeout, $exception->getPrevious());
        }
    }
}
