<?php

declare(strict_types=1);

namespace Module\Scenario\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Module\Scenario\DTO\SurveyIndexData;
use Module\Scenario\Services\SurveysService;

final class SurveysController extends Controller
{
    public function __construct(
        private readonly SurveysService $surveys,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return new JsonResponse($this->surveys->list(SurveyIndexData::fromRequest($request)));
    }

    public function show(string $runId): JsonResponse
    {
        return new JsonResponse($this->surveys->get($runId));
    }
}
