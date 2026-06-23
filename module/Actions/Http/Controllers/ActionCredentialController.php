<?php

declare(strict_types=1);

namespace Module\Actions\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Module\Actions\DTO\ActionCredentialData;
use Module\Actions\Http\Requests\ActionCredentialRequest;
use Module\Actions\Models\ActionCredential;
use Module\Actions\Services\ActionCredentialService;

final class ActionCredentialController extends Controller
{
    public function __construct(
        private readonly ActionCredentialService $credentialService,
    ) {}

    public function index(): JsonResponse
    {
        return new JsonResponse([
            'items' => $this->credentialService->items(),
        ]);
    }

    public function store(ActionCredentialRequest $request): JsonResponse
    {
        return new JsonResponse([
            'item' => $this->credentialService->create(ActionCredentialData::fromRequest($request)),
        ], 201);
    }

    public function update(ActionCredentialRequest $request, ActionCredential $credential): JsonResponse
    {
        return new JsonResponse([
            'item' => $this->credentialService->update(
                ActionCredentialData::fromRequest($request, $credential),
                $credential,
            ),
        ]);
    }

    public function destroy(Request $request, ActionCredential $credential): JsonResponse
    {
        $this->credentialService->delete(
            canManageActions: $request->user() !== null,
            credential: $credential,
        );

        return new JsonResponse(status: 204);
    }
}
