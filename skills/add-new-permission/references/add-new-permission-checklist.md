# Add New Permission — Quick Checklist

Replace `MyModule`, `my_module` with actual names.

## Enum (`module/<Module>/Enums/<Module>Permission.php`)

- [ ] `enum MyModulePermission: string implements PermissionEnum`
- [ ] Case values follow pattern `<module_prefix>_<action>` — globally unique snake_case strings
- [ ] Standard cases: `View = 'x_view'`, `Create = 'x_create'`, `Delete = 'x_delete'`
- [ ] `label(): string` — Russian human-readable action name
- [ ] `group(): string` — UI group heading (one per module, shown in Roles page)

## Registry (`module/Users/PermissionRegistry.php`)

- [ ] Import the new enum class
- [ ] Add `MyModulePermission::class` to the `list()` array

## Database sync

- [ ] Run `php artisan db:seed --class=PermissionSeeder`
- [ ] Run `php artisan db:seed --class=RoleSeeder` to grant new permissions to the administrator role
- [ ] Verify rows exist in `permissions` table

## Usage in code

- [ ] Form Request `authorize()`: `$this->user()?->hasPermissionTo(MyModulePermission::View->value)`
- [ ] Controller gate: `abort_unless($request->user()?->hasPermissionTo(...), 403)`
- [ ] DTO capability flag: `canManage: (bool) $request->user()?->hasPermissionTo(MyModulePermission::Create->value)`
- [ ] Frontend: read `canManage` from page props or API response to show/hide controls

## Renaming an existing permission

- [ ] Create a migration: `DB::table('permissions')->where('name', $old)->update(['name' => $new])`
- [ ] Update the enum case value to match the new name
- [ ] Re-run `PermissionSeeder` and `RoleSeeder`

## Decision Table

| Situation | What to do |
|---|---|
| New module with CRUD | Add `View`, `Create`, `Delete` cases |
| Action doesn't fit View/Create/Delete | Add a custom case, e.g. `case Export = 'my_module_export'` |
| Permission string must change | Write a migration — never change enum value without migrating DB |
| Check permission in a service (not controller) | Pass `$user->hasPermissionTo(...)` result as a bool arg; don't inject `Request` into services |
| Frontend needs to know user can edit | Add `canManage` flag to API/Inertia response, derived from `Create` permission |
