<?php

declare(strict_types=1);

namespace Module\Proxy\Proxies\AutoCrm;

use Module\Proxy\DTO\ProxyContext;
use Module\Proxy\DTO\ProxyFieldInteger;
use Module\Proxy\DTO\ProxyFieldString;
use Module\Proxy\DTO\ProxyResponse;
use Module\Proxy\Gateway\AutoCrm\AutoCrmGateway;
use Module\Proxy\Gateway\AutoCrm\DTO\AutoCrmData;

final class DealersProxyHandler extends AutoCrmEndpointHandler
{
    public function requestFields(): iterable
    {
        return [];
    }

    /** @return iterable<mixed> */
    #[\Override]
    public function responseFields(): iterable
    {
        yield ProxyFieldInteger::make('id')->label('ID дилера')->identity();
        yield ProxyFieldString::make('code')->label('Код дилера');
        yield ProxyFieldString::make('name')->label('Дилер');
        yield ProxyFieldString::make('marketing_name')->label('Маркетинговое название');
        yield ProxyFieldInteger::make('city_id')->label('ID города');
        yield ProxyFieldString::make('city')->label('Город');
        yield ProxyFieldString::make('address')->label('Адрес');
        yield ProxyFieldString::make('service_address')->label('Адрес СТО');
        yield ProxyFieldString::make('phone')->label('Телефон');
        yield ProxyFieldInteger::make('distributor_id')->label('ID дистрибьютора');
    }

    /**
     * @throws \Throwable
     */
    #[\Override]
    public function handle(ProxyContext $proxyContext): ProxyResponse
    {
        $autoCrm = $this->autoCrm($proxyContext);
        $dealers = $autoCrm->dealers();
        $cityNames = $this->cityNames($autoCrm, $dealers);

        $items = array_map(static fn (AutoCrmData $dealer): array => [
            'id' => $dealer->id(),
            'code' => $dealer->get('code'),
            'name' => $dealer->get('name'),
            'marketing_name' => $dealer->get('marketing_name'),
            'city_id' => $dealer->get('city_id'),
            'city' => (static function () use ($dealer, $cityNames): ?string {
                $v = $dealer->get('city_id');

                return is_scalar($v) ? ($cityNames[intval($v)] ?? null) : null;
            })(),
            'address' => $dealer->get('address'),
            'service_address' => $dealer->get('service_address'),
            'phone' => $dealer->get('phone'),
            'distributor_id' => $dealer->get('distributor_id'),
        ], $dealers);

        return ProxyResponse::accepted([
            'request_id' => $proxyContext->requestId(),
            'items' => $items,
        ]);
    }

    /**
     * @param  AutoCrmData[]      $dealers
     * @return array<int, string>
     *
     * @throws \Throwable
     */
    private function cityNames(AutoCrmGateway $autoCrm, array $dealers): array
    {
        $cityIds = [];
        foreach ($dealers as $dealer) {
            $v = $dealer->get('city_id');
            if (is_int($v) || is_string($v)) {
                $cityIds[] = $v;
            }
        }
        $cityIds = array_values(array_unique($cityIds));

        if ($cityIds === []) {
            return [];
        }

        $cities = $autoCrm->cities();
        $result = [];

        foreach ($cities as $city) {
            $id = $city->id();
            if ($id !== null && in_array($id, $cityIds, strict: false)) {
                $name = $city->get('name');
                $result[intval($id)] = is_scalar($name) ? (string) $name : '';
            }
        }

        return $result;
    }
}
