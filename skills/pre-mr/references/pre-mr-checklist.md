# Pre-MR Checklist

## Backend

```bash
task test
task rector:check -- module/<Module>
task phpstan -- module/<Module>
```

- [ ] Все тесты зелёные
- [ ] PHPStan: 0 ошибок
- [ ] Rector dry-run: предложения просмотрены, нужные изменения применены

## Frontend

```bash
task lint:fix
task typecheck
task lint
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
