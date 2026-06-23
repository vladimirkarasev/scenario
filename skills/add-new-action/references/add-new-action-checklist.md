# Add New Action — Quick Checklist

- [ ] Добавлен case в `module/Actions/Enums/ActionType.php`
- [ ] Создан `module/Actions/Services/Handlers/<Type>ActionHandler.php`
  - [ ] `implements ActionHandlerInterface`
  - [ ] `final class`, зависимости `private readonly`
  - [ ] Вызывает `$this->dataResolver->resolve($action->config ?? [], $input)`
  - [ ] Возвращает `ActionResult::success(...)`, `::failed(...)` или `::skipped(...)`
  - [ ] Не бросает исключения — ловит их и возвращает `failed`
- [ ] Зарегистрирован в `ActionRegistry::handlerFor()` match-expression
- [ ] PHPStan: `./vendor/bin/phpstan analyse module/Actions --memory-limit=512M`
