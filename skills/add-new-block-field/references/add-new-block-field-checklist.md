# Add New Block Field — checklist

## Contract

- [ ] `BlockFieldType`, field interface и `BlockField` union обновлены.
- [ ] `createScenarioBlockField()` задаёт все defaults.
- [ ] Normalizer принимает raw JSON и preset без top-level `id`.
- [ ] `duplicateBlockFieldIds()` меняет owned IDs и remap внутренних ссылок, сохраняя external IDs.
- [ ] `BlockFieldType.php` содержит новый case.

## Editor и player

- [ ] Settings лежит в `components/block-editor/field-settings/` и отправляет typed partial patch.
- [ ] `fieldMeta` зарегистрирован в palette/hidden list, component — в `fieldSettingsComponents`.
- [ ] Сложный player вынесен в отдельный компонент; HTTP идёт через composable/repository.
- [ ] Input обновляет только `formData[fieldName]`, показывает error и disabled state.
- [ ] Нет deep watcher на поле, форму или весь document.

## Backend Strategy

- [ ] Новый `XxxBlockField` добавлен в `Services/Nodes/Block/Fields/`.
- [ ] `BlockFieldFactory` нормализует type и выбирает Strategy.
- [ ] Handler не содержит второго type-switch и не знает детали props.
- [ ] Зависимости передаются через DI; отсутствуют `app()`, `resolve()`, `request()`.
- [ ] Validation добавлена только для input и сложная проверка вынесена в `ValidationRule`.

## Поведение и проверки

- [ ] Две вставки preset независимы, включая nested IDs.
- [ ] Missing external resource не ломает settings/player.
- [ ] Добавлены frontend tests defaults/normalization/duplication.
- [ ] Добавлены backend tests props/validation.
- [ ] Прошли `task typecheck`, scenario lint, PHPStan, целевые тесты и frontend build.
