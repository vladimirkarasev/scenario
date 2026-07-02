<?php

declare(strict_types=1);

namespace Module\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Module\Users\Services\PermissionCatalogService;

final class PermissionController extends Controller
{
    public function __construct(
        private readonly PermissionCatalogService $catalog,
    ) {
    }

    public function index(): JsonResponse
    {
        return new ApiResponse($this->catalog->list());
    }
}
