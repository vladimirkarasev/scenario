---
name: prefer-php-attributes
description: Использовать PHP 8 / Laravel атрибуты по максимуму вместо императивных свойств и методов-переопределений — класс становится декларативным и чище. Применять при создании или правке моделей Eloquent и любых классов, где конфигурация может быть выражена атрибутом.
---

# Prefer PHP Attributes

Конфигурацию класса выражаем **атрибутами**, а не свойствами/методами-переопределениями,
где это поддерживается. Так класс декларативен и выглядит лучше (Laravel 13, PHP 8.5).

## `#[\Override]` — всегда

На каждом методе, переопределяющем родительский, ставим `#[\Override]`
(в проекте это уже норма, >130 применений).

```php
#[\Override]
protected function casts(): array { /* ... */ }
```

## Eloquent: атрибут вместо свойства/метода

Конфиг модели выносим в атрибуты класса:

| Было (императивно) | Стало (атрибут) |
|---|---|
| `protected $table = 'user_groups';` | `#[Table('user_groups')]` |
| `protected $fillable = ['a', 'b'];` | `#[Fillable('a', 'b')]` |
| `protected $hidden = ['token'];` | `#[Hidden('token')]` |
| `protected $appends = ['x'];` | `#[Appends('x')]` |
| `protected $touches = ['post'];` | `#[Touches('post')]` |
| override `newEloquentBuilder()` | `#[UseEloquentBuilder(UserGroupBuilder::class)]` |
| глобальный scope | `#[ScopedBy(ActiveScope::class)]` |
| наблюдатель | `#[ObservedBy(UserObserver::class)]` |
| политика | `#[UsePolicy(UserPolicy::class)]` |
| коллекция ресурса | `#[CollectedBy(...)]` / `#[UseResource(...)]` |

Атрибуты `Fillable`/`Hidden`/`Appends`/`Touches` принимают вариадик: `#[Fillable('a', 'b', 'c')]`.

### Пример

```php
#[Table('user_groups')]
#[Fillable('site_id', 'name', 'slug', 'ext_id', 'description', 'is_active')]
#[UseEloquentBuilder(UserGroupBuilder::class)]
final class UserGroup extends Model
{
    use HasUuids;

    /** @return BelongsToMany<User, UserGroup> */
    public function members(): BelongsToMany { /* ... */ }

    #[\Override]
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
```

## Границы

- **Касты** остаются методом `casts()` — атрибута для них нет (только `#[\Override]` сверху).
- **Связи** (`belongsTo`, `hasMany`, …) остаются методами.
- Не изобретать атрибуты там, где их нет в фреймворке; не жертвовать читаемостью ради атрибута.

## Related

- [[code-comments]] — докблоки у методов
- [[static-analysis]] — PHPStan level 10
- [[add-crud-jsonapi]] — модели в CRUD-эндпоинтах
