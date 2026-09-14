<?php

declare(strict_types=1);

namespace Module\Scenario\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\Request;
use Module\Scenario\DTO\SurveyIndexData;
use Module\Scenario\Services\Runtime\SurveysService;

final class SurveysController extends Controller
{
    public function __construct(
        private readonly SurveysService $surveys,
    ) {
    }

    public function index(Request $request): ApiResponse
    {
        $result = $this->surveys->list(SurveyIndexData::fromRequest($request));

        return new ApiResponse($result['surveys'], meta: $result['pagination']);
    }

    public function show(string $runId): ApiResponse
    {
        return new ApiResponse($this->surveys->get($runId));
    }
}
