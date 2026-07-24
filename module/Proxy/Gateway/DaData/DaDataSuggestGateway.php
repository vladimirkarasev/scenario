<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\DaData;

use Module\Proxy\Gateway\Base\DTO\ApiGatewayConfig;
use Module\Proxy\Gateway\Base\DTO\ApiGatewayResponse;
use Module\Proxy\Gateway\Base\Services\BaseApiGateway;
use Module\Proxy\Gateway\Base\Transports\GuzzleApiTransport;
use Module\Proxy\Gateway\Base\Transports\MockApiTransport;
use Module\Proxy\Gateway\DaData\DTO\DaDataSuggestion;
use Module\Proxy\Gateway\DaData\Methods\FindAffiliatedMethod;
use Module\Proxy\Gateway\DaData\Methods\FindByIdMethod;
use Module\Proxy\Gateway\DaData\Methods\GeolocateMethod;
use Module\Proxy\Gateway\DaData\Methods\IplocateMethod;
use Module\Proxy\Gateway\DaData\Methods\SuggestMethod;
use Psr\Log\LoggerInterface;

final class DaDataSuggestGateway extends BaseApiGateway
{
    public function __construct(
        ApiGatewayConfig $config,
        GuzzleApiTransport $transport,
        MockApiTransport $mockTransport,
        LoggerInterface $logger,
    ) {
        parent::__construct(
            config: $config,
            transport: $transport,
            mockTransport: $mockTransport,
            logger: $logger,
        );
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, DaDataSuggestion>
     * @throws \Throwable
     */
    public function suggestAddress(string $query, int $count = 5, array $options = []): array
    {
        return $this->suggest('address', $query, $count, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, DaDataSuggestion>
     * @throws \Throwable
     */
    public function suggestParty(string $query, int $count = 5, array $options = []): array
    {
        return $this->suggest('party', $query, $count, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, DaDataSuggestion>
     * @throws \Throwable
     */
    public function suggestFio(string $query, int $count = 5, array $options = []): array
    {
        return $this->suggest('fio', $query, $count, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, DaDataSuggestion>
     * @throws \Throwable
     */
    public function suggestBank(string $query, int $count = 5, array $options = []): array
    {
        return $this->suggest('bank', $query, $count, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, DaDataSuggestion>
     * @throws \Throwable
     */
    public function suggestPostalUnit(string $query, int $count = 5, array $options = []): array
    {
        return $this->suggest('postal_unit', $query, $count, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, DaDataSuggestion>
     * @throws \Throwable
     */
    public function suggestCountry(string $query, int $count = 5, array $options = []): array
    {
        return $this->suggest('country', $query, $count, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, DaDataSuggestion>
     * @throws \Throwable
     */
    public function suggestEmail(string $query, int $count = 5, array $options = []): array
    {
        return $this->suggest('email', $query, $count, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, DaDataSuggestion>
     * @throws \Throwable
     */
    public function suggestFmsUnit(string $query, int $count = 5, array $options = []): array
    {
        return $this->suggest('fms_unit', $query, $count, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, DaDataSuggestion>
     * @throws \Throwable
     */
    public function suggestRegionCourt(string $query, int $count = 5, array $options = []): array
    {
        return $this->suggest('region_court', $query, $count, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, DaDataSuggestion>
     * @throws \Throwable
     */
    public function suggestMetro(string $query, int $count = 5, array $options = []): array
    {
        return $this->suggest('metro', $query, $count, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, DaDataSuggestion>
     * @throws \Throwable
     */
    public function suggestCarBrand(string $query, int $count = 5, array $options = []): array
    {
        return $this->suggest('car_brand', $query, $count, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, DaDataSuggestion>
     * @throws \Throwable
     */
    public function suggestCurrency(string $query, int $count = 5, array $options = []): array
    {
        return $this->suggest('currency', $query, $count, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, DaDataSuggestion>
     * @throws \Throwable
     */
    public function suggestOkved2(string $query, int $count = 5, array $options = []): array
    {
        return $this->suggest('okved2', $query, $count, $options);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, DaDataSuggestion>
     * @throws \Throwable
     */
    public function suggestOkpd2(string $query, int $count = 5, array $options = []): array
    {
        return $this->suggest('okpd2', $query, $count, $options);
    }

    /**
     * @return array<int, DaDataSuggestion>
     * @throws \Throwable
     */
    public function findAddressById(string $fiasOrKladrId, int $count = 1): array
    {
        return $this->findById('address', $fiasOrKladrId, $count);
    }

    /**
     * @return array<int, DaDataSuggestion>
     * @throws \Throwable
     */
    public function findFiasById(string $fiasId, int $count = 1): array
    {
        return $this->findById('fias', $fiasId, $count);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, DaDataSuggestion>
     * @throws \Throwable
     */
    public function findPartyById(string $inn, int $count = 1, array $options = []): array
    {
        return $this->findById('party', $inn, $count, $options);
    }

    /**
     * @return array<int, DaDataSuggestion>
     * @throws \Throwable
     */
    public function findBankById(string $bicOrSwiftOrInn, int $count = 1): array
    {
        return $this->findById('bank', $bicOrSwiftOrInn, $count);
    }

    /**
     * @return array<int, DaDataSuggestion>
     * @throws \Throwable
     */
    public function findPostalUnitById(string $postalCode, int $count = 1): array
    {
        return $this->findById('postal_unit', $postalCode, $count);
    }

    /**
     * @return array<int, DaDataSuggestion>
     * @throws \Throwable
     */
    public function findDeliveryById(string $kladrId, int $count = 1): array
    {
        return $this->findById('delivery', $kladrId, $count);
    }

    /**
     * @return array<int, DaDataSuggestion>
     * @throws \Throwable
     */
    public function findFnsUnitById(string $taxOfficeCode, int $count = 1): array
    {
        return $this->findById('fns_unit', $taxOfficeCode, $count);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, DaDataSuggestion>
     * @throws \Throwable
     */
    public function findAffiliated(string $inn, int $count = 5, array $options = []): array
    {
        return $this->suggestions($this->send(new FindAffiliatedMethod($inn, $count, $options)));
    }

    /**
     * @return array<int, DaDataSuggestion>
     * @throws \Throwable
     */
    public function geolocateAddress(float $lat, float $lon, int $radiusMeters = 100, int $count = 5): array
    {
        return $this->suggestions($this->send(new GeolocateMethod('address', $lat, $lon, $radiusMeters, $count)));
    }

    /**
     * @return array<int, DaDataSuggestion>
     * @throws \Throwable
     */
    public function geolocatePostalUnit(float $lat, float $lon, int $radiusMeters = 100, int $count = 5): array
    {
        return $this->suggestions($this->send(new GeolocateMethod('postal_unit', $lat, $lon, $radiusMeters, $count)));
    }

    /** @throws \Throwable */
    public function iplocate(string $ip): ?DaDataSuggestion
    {
        $response = $this->send(new IplocateMethod($ip));
        $location = $response->json('location');

        return is_array($location) ? DaDataSuggestion::fromArray($location) : null;
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, DaDataSuggestion>
     * @throws \Throwable
     */
    private function suggest(string $type, string $query, int $count, array $options): array
    {
        return $this->suggestions($this->send(new SuggestMethod($type, $query, $count, $options)));
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<int, DaDataSuggestion>
     * @throws \Throwable
     */
    private function findById(string $type, string $query, int $count, array $options = []): array
    {
        return $this->suggestions($this->send(new FindByIdMethod($type, $query, $count, $options)));
    }

    /** @return array<int, DaDataSuggestion> */
    private function suggestions(ApiGatewayResponse $response): array
    {
        $items = $response->json('suggestions');

        if (! is_array($items)) {
            return [];
        }

        return array_values(array_map(
            DaDataSuggestion::fromArray(...),
            array_filter($items, 'is_array'),
        ));
    }
}
