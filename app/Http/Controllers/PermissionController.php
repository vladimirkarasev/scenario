<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Module\Users\PermissionRegistry;

final class PermissionController extends Controller
{
    public function index(): JsonResponse
    {
        $permissions = [];

        foreach (PermissionRegistry::list() as $enumClass) {
            foreach ($enumClass::cases() as $case) {
                $permissions[] = [
                    'name' => $case->value,
                    'label' => $case->label(),
                    'group' => $case->group(),
                ];
            }
        }

        usort($permissions, static fn (array $a, array $b): int => $a['name'] <=> $b['name']);

        return new JsonResponse(['data' => $permissions]);
    }
}
