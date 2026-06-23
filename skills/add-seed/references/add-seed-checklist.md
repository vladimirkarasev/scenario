# Database Seeding — Quick Checklist

## Новый сидер

- [ ] `final class XxxSeeder extends Seeder`
- [ ] Только `updateOrCreate` / `firstOrCreate` — никакого `create()` без проверки
- [ ] Никакого `truncate()`
- [ ] Добавлен в `DatabaseSeeder::run()` после зависимостей

## После добавления нового permission

```bash
php artisan db:seed --class=PermissionSeeder
php artisan db:seed --class=RoleSeeder
```

## После добавления proxy-эндпоинта

```bash
php artisan proxies:sync
```

## Первоначальная настройка

```bash
php artisan db:seed
# или
php artisan migrate:fresh --seed  # только local — сбрасывает данные!
```

## В Docker

```bash
task artisan -- db:seed --class=PermissionSeeder
task artisan -- db:seed --class=RoleSeeder
```

## Тестовые аккаунты (после seeding)

| Email | Логин | Пароль |
|---|---|---|
| admin@scenario.local | admin | password |
| operator@scenario.local | operator | password |
| manager@scenario.local | manager | password |
