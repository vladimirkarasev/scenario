<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\DTO\Expression\RenderExpressionData;
use App\Http\Requests\Expression\RenderExpressionRequest;
use App\Http\Responses\ApiResponse;
use App\Services\Expression\ExpressionRenderService;

final class ExpressionRenderController extends Controller
{
    public function __construct(
        private readonly ExpressionRenderService $renderer,
    ) {
    }

    public function __invoke(RenderExpressionRequest $request): ApiResponse
    {
        return new ApiResponse(
            $this->renderer->render(RenderExpressionData::fromRequest($request))->toArray(),
        );
    }
}
