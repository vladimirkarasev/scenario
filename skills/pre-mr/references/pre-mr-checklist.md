# Pre-MR Checklist

## Backend

```bash
composer run test
./vendor/bin/pint module/<Module>
./vendor/bin/phpstan analyse module/<Module> --memory-limit=512M --error-format=table
```

- [ ] Все тесты зелёные
- [ ] PHPStan: 0 ошибок
- [ ] Pint: нет изменений (или изменения закоммичены)

## Frontend

```bash
npm run lint:fix
npx tsc --noEmit
npm run lint
```

- [ ] TypeScript: 0 ошибок
- [ ] ESLint: 0 ошибок

## Код

- [ ] Нет `dd()` / `dump()` / `console.log()`
- [ ] Нет закомментированного кода
- [ ] Нет TODO которые нужно было решить в этом MR

## Интеграции

- [ ] Новые роуты есть в `routes/api.php` модуля
- [ ] Новые permissions → enum + `PermissionRegistry` + seeders запущены
- [ ] Новый proxy-эндпоинт → `php artisan proxies:sync` запущен
- [ ] Новые env-переменные → добавлены в `.env.example`
- [ ] Изменилась схема БД → миграция создана

## MR description

```markdown
## Что изменено

## Как проверить

## Миграции / команды

## Скриншоты
```
