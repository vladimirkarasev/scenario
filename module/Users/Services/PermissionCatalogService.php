<?php

declare(strict_types=1);

namespace Module\Users\Services;

use App\Support\PermissionRegistry;

final class PermissionCatalogService
{
    /**
     * Плоский справочник всех разрешений, отсортированный по имени.
     *
     * @return list<array{name: string, label: string, group: string}>
     */
    public function list(): array
    {
        $permissions = [];

        foreach (PermissionRegistry::list() as $enumClass) {
            foreach ($enumClass::cases() as $case) {
                $permissions[] = [
                    'name' => (string) $case->value,
                    'label' => $case->label(),
                    'group' => $case->group(),
                ];
            }
        }

        usort($permissions, static fn(array $a, array $b): int => $a['name'] <=> $b['name']);

        return $permissions;
    }
}
