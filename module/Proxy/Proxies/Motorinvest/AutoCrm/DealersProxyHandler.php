<?php

declare(strict_types=1);

namespace Module\Proxy\Proxies\Motorinvest\AutoCrm;

use Module\Proxy\DTO\ProxyContext;
use Module\Proxy\DTO\ProxyResponse;
use Module\Proxy\Gateway\AutoCrm\DTO\AutoCrmData;
use Module\Proxy\Gateway\AutoCrm\MotorinvestAutoCrmGateway;
use Module\Proxy\Proxies\Base\AutoCrm\DealersProxyHandler as BaseDealersProxyHandler;

final class DealersProxyHandler extends BaseDealersProxyHandler
{
    public function __construct(private readonly MotorinvestAutoCrmGateway $autoCrm) {}

    /**
     * @throws \Throwable
     */
    #[\Override]
    public function handle(ProxyContext $proxyContext): ProxyResponse
    {
        $dealers = $this->autoCrm->dealers();
        $cityNames = $this->cityNames($dealers);

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
    private function cityNames(array $dealers): array
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

        $cities = $this->autoCrm->cities();
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
