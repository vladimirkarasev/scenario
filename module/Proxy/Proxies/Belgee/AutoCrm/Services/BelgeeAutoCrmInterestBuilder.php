<?php

declare(strict_types=1);

namespace Module\Proxy\Proxies\Belgee\AutoCrm\Services;

use Module\Proxy\DTO\ProxyContext;
use Module\Proxy\Gateway\AutoCrm\DTO\InterestForm;
use Module\Proxy\Proxies\Belgee\AutoCrm\DTO\BelgeeAutoCrmSettings;

final readonly class BelgeeAutoCrmInterestBuilder
{
    public function build(ProxyContext $proxyContext): InterestForm
    {
        $settings = BelgeeAutoCrmSettings::fromProxyContext($proxyContext);
        $nameRaw = $proxyContext->data('name', '');
        $name = is_string($nameRaw) ? $nameRaw : '';

        return InterestForm::fromArray(array_filter([
            'request_type_id' => $settings->requiredId('request_type_id'),
            'source_id' => $settings->requiredId('source_id'),
            'first_name' => $proxyContext->data('first_name') ?: $this->firstName($name),
            'last_name' => $proxyContext->data('last_name') ?: $this->lastName($name),
            'phone' => $proxyContext->data('phone'),
            'email' => $proxyContext->data('email'),
            'brand_id' => $settings->id('brand_id'),
            'model_id' => $this->id($proxyContext->data('model_id')),
            'city_id' => $this->id($proxyContext->data('city_id')) ?? $settings->id('city_id'),
            'dealer_id' => $settings->id('dealer_id'),
            'distributor_id' => $settings->id('distributor_id'),
            'executor_category_id' => $settings->id('executor_category_id'),
            'result_id' => $settings->id('result_id'),
            'comment' => $proxyContext->data('comment') ?: 'Belgee webhook '.$proxyContext->requestId(),
            'external_request_id' => $proxyContext->data('external_request_id'),
            'meta' => [
                'source' => $proxyContext->data('source', 'webhook'),
                'request_id' => $proxyContext->requestId(),
                'endpoint_code' => $proxyContext->endpoint->code,
            ],
        ], static fn (mixed $value): bool => $value !== null && $value !== ''));
    }

    private function id(mixed $value): int|string|null
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            return ctype_digit($value) ? (int) $value : $value;
        }

        return null;
    }

    private function firstName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return (string) ($parts[0] ?? 'Belgee');
    }

    private function lastName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];

        return (string) ($parts[1] ?? 'Lead');
    }
}
