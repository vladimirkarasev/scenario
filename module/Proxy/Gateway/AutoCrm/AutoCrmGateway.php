<?php

declare(strict_types=1);

namespace Module\Proxy\Gateway\AutoCrm;

use Module\Proxy\Gateway\AutoCrm\DTO\AutoCrmData;
use Module\Proxy\Gateway\AutoCrm\DTO\AutoCrmListQuery;
use Module\Proxy\Gateway\AutoCrm\DTO\InterestForm;
use Module\Proxy\Gateway\AutoCrm\Methods\AutoCrmListPageMethod;
use Module\Proxy\Gateway\AutoCrm\Methods\Brand\GetBrandListPageMethod;
use Module\Proxy\Gateway\AutoCrm\Methods\Brand\GetBrandMethod;
use Module\Proxy\Gateway\AutoCrm\Methods\City\GetCityListPageMethod;
use Module\Proxy\Gateway\AutoCrm\Methods\City\GetCityMethod;
use Module\Proxy\Gateway\AutoCrm\Methods\CreateLeadMethod;
use Module\Proxy\Gateway\AutoCrm\Methods\Dealer\GetDealerListPageMethod;
use Module\Proxy\Gateway\AutoCrm\Methods\Dealer\GetDealerMethod;
use Module\Proxy\Gateway\AutoCrm\Methods\Distributor\GetDistributorListPageMethod;
use Module\Proxy\Gateway\AutoCrm\Methods\Distributor\GetDistributorMethod;
use Module\Proxy\Gateway\AutoCrm\Methods\ExecutorCategory\GetExecutorCategoryListPageMethod;
use Module\Proxy\Gateway\AutoCrm\Methods\ExecutorCategory\GetExecutorCategoryMethod;
use Module\Proxy\Gateway\AutoCrm\Methods\Interest\CreateInterestMethod;
use Module\Proxy\Gateway\AutoCrm\Methods\Interest\GetInterestListPageMethod;
use Module\Proxy\Gateway\AutoCrm\Methods\Interest\GetInterestMethod;
use Module\Proxy\Gateway\AutoCrm\Methods\Model\GetModelListPageMethod;
use Module\Proxy\Gateway\AutoCrm\Methods\Model\GetModelMethod;
use Module\Proxy\Gateway\AutoCrm\Methods\RequestType\GetRequestTypeListPageMethod;
use Module\Proxy\Gateway\AutoCrm\Methods\RequestType\GetRequestTypeMethod;
use Module\Proxy\Gateway\AutoCrm\Methods\Result\GetResultListPageMethod;
use Module\Proxy\Gateway\AutoCrm\Methods\Result\GetResultMethod;
use Module\Proxy\Gateway\Base\Contracts\ApiMethod;
use Module\Proxy\Gateway\Base\DTO\ApiGatewayConfig;
use Module\Proxy\Gateway\Base\DTO\ApiGatewayResponse;
use Module\Proxy\Gateway\Base\Services\BaseApiGateway;
use Module\Proxy\Gateway\Base\Transports\GuzzleApiTransport;
use Module\Proxy\Gateway\Base\Transports\MockApiTransport;
use Psr\Log\LoggerInterface;

class AutoCrmGateway extends BaseApiGateway
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
     * @param  array<string, mixed>  $lead
     * @throws \Throwable
     */
    public function createLead(array $lead): ApiGatewayResponse
    {
        return $this->send(new CreateLeadMethod($lead));
    }

    /**
     * @return array<int, AutoCrmData>
     * @throws \Throwable
     */
    public function brands(?AutoCrmListQuery $query = null): array
    {
        return $this->list(GetBrandListPageMethod::class, $query ?? new AutoCrmListQuery);
    }

    /**
     * @throws \Throwable
     */
    public function brand(int $id): AutoCrmData
    {
        return $this->one(new GetBrandMethod($id));
    }

    /**
     * @return array<int, AutoCrmData>
     * @throws \Throwable
     */
    public function cities(?AutoCrmListQuery $query = null): array
    {
        return $this->list(GetCityListPageMethod::class, $query ?? new AutoCrmListQuery);
    }

    /**
     * @throws \Throwable
     */
    public function city(int $id): AutoCrmData
    {
        return $this->one(new GetCityMethod($id));
    }

    /**
     * @return array<int, AutoCrmData>
     * @throws \Throwable
     */
    public function dealers(?AutoCrmListQuery $query = null): array
    {
        return $this->list(GetDealerListPageMethod::class, $query ?? new AutoCrmListQuery);
    }

    /**
     * @throws \Throwable
     */
    public function dealer(int $id): AutoCrmData
    {
        return $this->one(new GetDealerMethod($id));
    }

    /**
     * @return array<int, AutoCrmData>
     * @throws \Throwable
     */
    public function distributors(?AutoCrmListQuery $query = null): array
    {
        return $this->list(GetDistributorListPageMethod::class, $query ?? new AutoCrmListQuery);
    }

    /**
     * @throws \Throwable
     */
    public function distributor(int $id): AutoCrmData
    {
        return $this->one(new GetDistributorMethod($id));
    }

    /**
     * @return array<int, AutoCrmData>
     * @throws \Throwable
     */
    public function executorCategories(?AutoCrmListQuery $query = null): array
    {
        return $this->list(GetExecutorCategoryListPageMethod::class, $query ?? new AutoCrmListQuery);
    }

    /**
     * @throws \Throwable
     */
    public function executorCategory(int $id): AutoCrmData
    {
        return $this->one(new GetExecutorCategoryMethod($id));
    }

    /**
     * @return array<int, AutoCrmData>
     * @throws \Throwable
     */
    public function interests(?AutoCrmListQuery $query = null): array
    {
        return $this->list(GetInterestListPageMethod::class, $query ?? new AutoCrmListQuery);
    }

    /**
     * @throws \Throwable
     */
    public function interest(int $id): AutoCrmData
    {
        return $this->one(new GetInterestMethod($id));
    }

    /**
     * @param  InterestForm|array<string, mixed>  $form
     * @throws \Throwable
     */
    public function createInterest(InterestForm|array $form): AutoCrmData
    {
        $response = $this->send(
            new CreateInterestMethod(
                $form instanceof InterestForm ? $form : InterestForm::fromArray($form),
            )
        );

        return AutoCrmData::fromArray(is_array($response->body) ? $response->body : []);
    }

    /**
     * @return array<int, AutoCrmData>
     * @throws \Throwable
     */
    public function models(?AutoCrmListQuery $query = null): array
    {
        return $this->list(GetModelListPageMethod::class, $query ?? new AutoCrmListQuery);
    }

    /**
     * @throws \Throwable
     */
    public function model(int $id, ?string $expand = null): AutoCrmData
    {
        return $this->one(new GetModelMethod($id, $expand));
    }

    /**
     * @return array<int, AutoCrmData>
     * @throws \Throwable
     */
    public function requestTypes(?AutoCrmListQuery $query = null): array
    {
        return $this->list(GetRequestTypeListPageMethod::class, $query ?? new AutoCrmListQuery);
    }

    /**
     * @throws \Throwable
     */
    public function requestType(int $id): AutoCrmData
    {
        return $this->one(new GetRequestTypeMethod($id));
    }

    /**
     * @return array<int, AutoCrmData>
     */
    public function results(?AutoCrmListQuery $query = null): array
    {
        return $this->list(GetResultListPageMethod::class, $query ?? new AutoCrmListQuery);
    }

    /**
     * @throws \Throwable
     */
    public function result(int $id): AutoCrmData
    {
        return $this->one(new GetResultMethod($id));
    }

    /**
     * @param  class-string<AutoCrmListPageMethod>  $methodClass
     * @return array<int, AutoCrmData>
     * @throws \Throwable
     */
    private function list(string $methodClass, AutoCrmListQuery $query): array
    {
        $items = [];
        $lastPage = $query->firstPage;

        for ($page = $query->firstPage; $page <= $lastPage; $page++) {
            $response = $this->send(new $methodClass($query, $page));
            $pageItems = $this->items($response);

            if ($pageItems === []) {
                break;
            }

            $items = array_merge($items, $pageItems);
            $pageCount = $this->pageCount($response);

            if ($pageCount > 0) {
                $lastPage = $pageCount;
            }
        }

        return $items;
    }

    /**
     * @throws \Throwable
     */
    private function one(ApiMethod $method): AutoCrmData
    {
        $response = $this->send($method);

        return AutoCrmData::fromArray(is_array($response->body) ? $response->body : []);
    }

    /**
     * @return array<int, AutoCrmData>
     */
    private function items(ApiGatewayResponse $response): array
    {
        $body = is_array($response->body) ? $response->body : [];
        $arrays = array_filter($body, is_array(...));

        return array_values(
            array_map(
                AutoCrmData::fromArray(...),
                $arrays,
            )
        );
    }

    private function pageCount(ApiGatewayResponse $response): int
    {
        $header = $response->header('x-pagination-page-count');

        if (is_array($header)) {
            $header = $header[0] ?? null;
        }

        return $header !== null && is_scalar($header) ? intval($header) : 0;
    }
}
