<?php

declare(strict_types=1);

namespace Module\Scenario\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Module\Scenario\DTO\CatalogIndexData;
use Module\Scenario\Http\Resources\JsonApi\CatalogResource;
use Module\Scenario\Services\CatalogService;
use Module\Users\Models\User;

final class CatalogController extends Controller
{
    public function __construct(private readonly CatalogService $catalogService)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        return CatalogResource::collection($this->catalogService->paginate(
            CatalogIndexData::fromRequest($request),
            $user instanceof User ? $user : null,
        ));
    }
}
