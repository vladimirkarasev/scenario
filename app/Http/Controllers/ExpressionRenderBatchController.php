<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTO\Expression\RenderExpressionBatchData;
use App\Http\Requests\Expression\RenderExpressionBatchRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Expression\ExpressionRenderService;

final class ExpressionRenderBatchController extends Controller
{
    public function __construct(
        private readonly ExpressionRenderService $renderer,
    ) {
    }

    public function __invoke(RenderExpressionBatchRequest $request): ApiResponse
    {
        return new ApiResponse(
            $this->renderer->renderBatch(RenderExpressionBatchData::fromRequest($request))->toObject(),
        );
    }
}
