---
name: add-new-permission
description: Add new permissions to the permission system — backed enum implementing PermissionEnum, registration in PermissionRegistry, seeder sync, and usage in controllers/DTOs. Use when asked to add a permission, gate access to a new feature, add a role-based check, or create a new module's permission set.
---

# Add New Permission

## Overview

Permissions are built on **spatie/laravel-permission**. Each module defines its own backed enum implementing `PermissionEnum`. All enums are registered in one central `PermissionRegistry`. The seeder syncs enum values to the `permissions` table; the administrator role gets all permissions automatically.

Key files:
- Enum contract: `app/Contracts/PermissionEnum.php`
- Module enum: `module/<Module>/Enums/<Module>Permission.php`
- Central registry: `module/Users/PermissionRegistry.php`
- Seeder: `database/seeders/PermissionSeeder.php`

## Step-by-Step

### 1. Create the permission enum

File: `module/<Module>/Enums/<Module>Permission.php`

```php
<?php

declare(strict_types=1);

namespace Module\MyModule\Enums;

use App\Contracts\PermissionEnum;

enum MyModulePermission: string implements PermissionEnum
{
    case View   = 'my_module_view';
    case Create = 'my_module_create';
    case Delete = 'my_module_delete';

    public function label(): string
    {
        return match ($this) {
            self::View   => 'Просмотр',
            self::Create => 'Создание/Редактирование',
            self::Delete => 'Удаление',
        };
    }

    public function group(): string
    {
        return 'Название модуля (для UI)';
    }
}
```

Rules:
- Backed enum: `string`
- Implements `App\Contracts\PermissionEnum`
- Permission string values: `<module_prefix>_<action>` — snake_case, globally unique
- Standard action set: `view`, `create` (covers edit too), `delete`
- `label()` — human-readable Russian text shown in the Roles UI
- `group()` — UI group heading in the permissions list; one group per module

### 2. Register in PermissionRegistry

File: `module/Users/PermissionRegistry.php`

```php
use Module\MyModule\Enums\MyModulePermission;

public static function list(): array
{
    return [
        UserPermission::class,
        // ... other enums
        MyModulePermission::class,   // ← add here
    ];
}
```

This registration makes the enum visible to:
- `PermissionSeeder` — syncs enum values to the `permissions` table
- `PermissionController::index()` — exposes permissions to the frontend Roles page

### 3. Sync to the database

After registering, run the seeder to create the DB rows:

```bash
php artisan db:seed --class=PermissionSeeder
```

The seeder calls `Permission::firstOrCreate()` for each case — safe to run multiple times. The administrator role already gets all permissions via `RoleSeeder`; re-run it too if you want to include the new permissions in an existing admin role immediately:

```bash
php artisan db:seed --class=RoleSeeder
```

### 4. Use in controllers or DTOs

#### In a Form Request

```php
public function authorize(): bool
{
    return (bool) $this->user()?->hasPermissionTo(MyModulePermission::Create->value);
}
```

#### In a controller method

```php
use Module\MyModule\Enums\MyModulePermission;

public function destroy(Request $request, MyModel $model): JsonResponse
{
    abort_unless(
        $request->user()?->hasPermissionTo(MyModulePermission::Delete->value),
        403,
    );

    $this->service->delete($model);
    return response()->json(null, 204);
}
```

#### In a DTO (for passing to frontend as a capability flag)

```php
canManage: (bool) $request->user()?->hasPermissionTo(MyModulePermission::Create->value),
```

Then in the Inertia page props or API response:
```php
return response()->json([
    'canManage' => $request->user()?->hasPermissionTo(MyModulePermission::Create->value),
    'items'     => $this->service->list(),
]);
```

The frontend reads `canManage` to show/hide edit buttons.

### 5. Renaming a permission (migration required)

If a permission value must change (e.g. module rename), create a migration — **do not change the enum value directly** without migrating, or existing role assignments will break:

```php
return new class extends Migration
{
    public function up(): void
    {
        DB::table('permissions')
            ->where('name', 'old_module_view')
            ->update(['name' => 'new_module_view']);
    }

    public function down(): void
    {
        DB::table('permissions')
            ->where('name', 'new_module_view')
            ->update(['name' => 'old_module_view']);
    }
};
```

Then update the enum case value to match.

## Verification

```bash
# After seeding, check rows exist:
php artisan tinker --execute="echo \Spatie\Permission\Models\Permission::where('name', 'like', 'my_module_%')->count();"

# Check the admin role has them:
php artisan tinker --execute="echo \App\Models\Role::where('name', 'administrator')->first()?->permissions->pluck('name');"
```

Also verify the Roles UI — navigate to `/roles` and confirm the new group and permissions appear in the role editor.

## References

See [references/add-new-permission-checklist.md](references/add-new-permission-checklist.md) for the quick checklist.
