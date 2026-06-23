# Add New Module — Quick Checklist

## Структура

- [ ] `module/<Module>/Providers/<Module>ServiceProvider.php`
- [ ] `module/<Module>/routes/api.php`
- [ ] `module/<Module>/routes/web.php` (если есть страницы)

## ServiceProvider

- [ ] `final class <Module>ServiceProvider extends ServiceProvider`
- [ ] `boot()` регистрирует api.php через `Route::middleware(['api', 'auth:sanctum'])->prefix('api')->group(...)`
- [ ] web.php через `Route::middleware('web')->group(...)` (если нужно)

## Регистрация

- [ ] Добавлен в `bootstrap/providers.php`

## Permissions (если нужны)

- [ ] `module/<Module>/Enums/<Module>Permission.php` — implements `PermissionEnum`
- [ ] Добавлен в `module/Users/PermissionRegistry.php`
- [ ] `php artisan db:seed --class=PermissionSeeder && php artisan db:seed --class=RoleSeeder`

## PHPStan / Pint

- [ ] `./vendor/bin/pint module/<Module>`
- [ ] `./vendor/bin/phpstan analyse module/<Module> --memory-limit=512M`

## Verification

```bash
php artisan route:list | grep <resource>
php artisan route:cache  # проверить что нет конфликтов
```
