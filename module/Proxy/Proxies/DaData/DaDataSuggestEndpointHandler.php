<?php

declare(strict_types=1);

namespace Module\Proxy\Proxies\DaData;

use Module\Proxy\Credentials\DaData\DaDataCredential;
use Module\Proxy\DTO\ProxyContext;
use Module\Proxy\DTO\ProxyField;
use Module\Proxy\DTO\ProxyFieldInteger;
use Module\Proxy\DTO\ProxyFieldString;
use Module\Proxy\DTO\ProxyResponse;
use Module\Proxy\Gateway\Base\Exceptions\ApiGatewayConfigException;
use Module\Proxy\Gateway\DaData\DaDataSuggestGateway;
use Module\Proxy\Gateway\DaData\DaDataSuggestGatewayFactory;
use Module\Proxy\Gateway\DaData\DTO\DaDataSuggestion;
use Module\Proxy\ProxyHandler;

abstract class DaDataSuggestEndpointHandler extends ProxyHandler
{
    public function __construct(private readonly DaDataSuggestGatewayFactory $gateways) {}

    #[\Override]
    public function credentialType(): string
    {
        return DaDataCredential::class;
    }

    public function requestFields(): iterable
    {
        yield ProxyFieldString::make('query')
            ->label('Запрос')
            ->required()
            ->rules(['required', 'string', 'max:255']);

        yield ProxyFieldInteger::make('count')
            ->label('Кол-во подсказок')
            ->nullable()
            ->default(5)
            ->rules(['nullable', 'integer', 'min:1', 'max:20']);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, DaDataSuggestion>
     */
    abstract protected function suggest(DaDataSuggestGateway $gateway, string $query, int $count, array $options): array;

    /** @throws \Throwable */
    #[\Override]
    public function handle(ProxyContext $proxyContext): ProxyResponse
    {
        $query = is_string($proxyContext->data('query')) ? $proxyContext->data('query') : '';
        $count = is_int($proxyContext->data('count')) ? $proxyContext->data('count') : 5;

        $items = $this->suggest($this->gateway($proxyContext), $query, $count, $this->requestOptions($proxyContext));

        return ProxyResponse::ok([
            'request_id' => $proxyContext->requestId(),
            'items' => array_map(static fn (DaDataSuggestion $s): array => [
                'value' => $s->value,
                'unrestricted_value' => $s->unrestrictedValue,
                'data' => $s->data,
            ], $items),
        ]);
    }

    /** @return array<string, mixed> */
    private function requestOptions(ProxyContext $proxyContext): array
    {
        $options = [];

        foreach ($this->requestFields() as $field) {
            if (! $field instanceof ProxyField || in_array($field->key(), ['query', 'count'], true)) {
                continue;
            }

            $value = $proxyContext->data($field->key());

            if ($value !== null) {
                $options[$field->key()] = $value;
            }
        }

        return $options;
    }

    private function gateway(ProxyContext $proxyContext): DaDataSuggestGateway
    {
        $connection = $proxyContext->endpoint->connection;

        if ($connection === null) {
            throw new ApiGatewayConfigException('DaData handler requires a connection with API credentials.');
        }

        return $this->gateways->forConnection($connection, $proxyContext->endpoint->is_mocked);
    }
}
