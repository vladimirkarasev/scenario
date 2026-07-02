---
name: api-response-contract
description: Единый контракт ответов API — конверт успеха {data, meta}, конверт ошибки {errors:[...], meta} с доменными исключениями и enum-кодами, meta с timestamp+requestId. Применять при создании или изменении любого API-эндпоинта, контроллера, ответа, обработки ошибок/исключений (backend) и при разборе ответов на фронте.
---

# API Response Contract

Единый формат ответов для API. Дополняет [[jsonapi-conventions]] (query-параметры,
`filter[]`/`page[]`) — здесь про **конверт ответа, meta и ошибки**.

Эталонная реализация — модуль `Users`.

## Правила (коротко)

- Любой **успешный** ответ = `{ "data": ..., "meta": { timestamp, requestId } }`.
- Любая **ошибка** = `{ "errors": [ { status, code, title, detail, source? } ], "meta": {...} }`.
- `meta` (timestamp + requestId) присутствует в **каждом** ответе — и в 200, и в ошибке.
- `204 No Content` — без тела (единственное исключение из «всё в data»).
- Полезная нагрузка **всегда** под `data`. Плоских ответов на верхнем уровне быть не должно.

## Success: `{ data, meta }`

`meta` добавляет middleware `App\Http\Middleware\AddApiMeta` (навешивается на группу
роутов модуля), поэтому в контроллере про `meta` думать не нужно — только про `data`.

**JSON:API ресурсы** (list/show/store/update) уже дают `data` сами — просто вернуть ресурс:

```php
return new UserResource($user);                 // { data: {...}, meta }
return UserResource::collection($paginator);     // { data: [...], meta, links }
```

**Плоские полезные нагрузки** (не-ресурсные ответы) — через `App\Http\Responses\ApiResponse`,
он сам оборачивает в `data`:

```php
use App\Http\Responses\ApiResponse;

return new ApiResponse([
    'id' => $user->id,
    'permissions' => $permissions,
]);                                              // { data: {...}, meta }
```

Не писать `new JsonResponse(['data' => ...])` вручную — использовать `ApiResponse`.

## Errors: `{ errors: [...], meta }`

Формат одной ошибки (JSON:API):

```json
{
  "errors": [
    { "status": "404", "code": "USER_NOT_FOUND", "title": "Пользователь не найден",
      "detail": "Пользователь не найден в проекте." }
  ],
  "meta": { "timestamp": "2026-07-02T10:00:00+00:00", "requestId": "a1b2..." }
}
```

Для ошибок валидации у каждого элемента есть `source.pointer`:

```json
{ "status": "422", "code": "VALIDATION_ERROR", "title": "Ошибка валидации",
  "detail": "The login field is required.", "source": { "pointer": "/data/attributes/login" } }
```

### Бросать доменные исключения, а не HttpException/abort

В сервисах/репозиториях бросаем классы из `app/Exceptions/`:

| Класс | Status |
|---|---|
| `App\Exceptions\ForbiddenException` | 403 |
| `App\Exceptions\NotFoundException` | 404 |
| `App\Exceptions\ConflictException` (нарушение бизнес-правила) | 422 |

База — `App\Exceptions\DomainException` (несёт `code`, `title`, `detail`, `status()`).

**Основной способ — `::from(ErrorCodeEnum)`**: без строк на call-site, весь текст в enum.

```php
throw ForbiddenException::from(UserErrorCode::SystemUserImmutable);
throw NotFoundException::from(UserErrorCode::UserNotFound);
throw ConflictException::from(UserErrorCode::LastAdministrator);
```

`::make($detail, $code, $title)` — только для **динамического** текста (интерполяция);
`$code` принимает `string | \BackedEnum`:

```php
throw ConflictException::make(sprintf('Роль «%s» не найдена.', $name), UserErrorCode::RoleNotFound);
```

Не использовать: `throw new HttpException(...)`, `abort(403)`, `->firstOrFail()`
(вместо последнего — `->first() ?? throw NotFoundException::from(...)`).

### Коды ошибок — backed enum на модуль, реализует `ErrorText`

Машиночитаемый `code` — это backed string enum, реализующий
`App\Contracts\ErrorText` (`code()` / `title()` / `detail()`). Не «магические строки».

```php
// module/<Module>/Enums/<Module>ErrorCode.php
enum UserErrorCode: string implements ErrorText
{
    case UserNotFound = 'USER_NOT_FOUND';
    case SystemUserImmutable = 'SYSTEM_USER_IMMUTABLE';
    // ...

    #[\Override] public function code(): string { return $this->value; }
    #[\Override] public function title(): string  { return (string) trans("errors.users.{$this->value}.title"); }
    #[\Override] public function detail(): string { return (string) trans("errors.users.{$this->value}.detail"); }
}
```

### Тексты ошибок — в lang, не в коде

Сами строки живут в `lang/<locale>/errors.php`, ключ = код:
`errors.<module>.<CODE>.{title|detail}`. Enum читает их через `trans()` — это
единственная точка локализации.

```php
// lang/ru/errors.php
return ['users' => [
    'USER_NOT_FOUND' => ['title' => 'Пользователь не найден', 'detail' => 'Пользователь не найден в проекте.'],
    // ...
]];
```

Локаль по умолчанию — `ru` (`config/app.php`, `.env`, `phpunit.xml`), `fallback_locale`
остаётся `en` (встроенные сообщения валидации Laravel). Английский = добавить
`lang/en/errors.php` с той же структурой; enum и throw-сайты не трогаем.

### Регистрация рендеринга в ServiceProvider модуля

Доменные исключения рендерим всегда; стандартные исключения фреймворка —
**только на роутах своего модуля** (guard по namespace контроллера), чтобы не менять
поведение остальных модулей. И навешиваем `AddApiMeta` на группу роутов.

```php
// <Module>ServiceProvider::boot()
Route::middleware(['api', 'auth:sanctum', /* ... */, AddApiMeta::class])
    ->prefix('api')->group(dirname(__DIR__).'/routes/api.php');

$this->registerExceptionHandlers();
```

```php
private function registerExceptionHandlers(): void
{
    $handler = $this->app->make(ExceptionHandler::class);

    // Доменные — всегда (для api/*).
    $handler->renderable(fn(DomainException $e, Request $r): ?JsonResponse =>
        $r->is('api/*') ? ApiErrorResponse::make([$e->toError()], $e->status(), $r) : null);

    // Фреймворковые — только на роутах модуля (guard по $r->route()->getActionName()):
    //   ValidationException(422, VALIDATION_ERROR + source.pointer),
    //   ModelNotFoundException(404), AuthorizationException|AccessDeniedHttpException(403),
    //   AuthenticationException(401), общий HttpExceptionInterface fallback (405/429/…).
}
```

Строить ответ ошибки — через `App\Http\Responses\ApiErrorResponse::make($errors, $status, $request)`.

## Инфраструктура (уже есть, переиспользовать)

- `App\Http\Responses\ApiResponse` — success-конверт `{ data }`.
- `App\Http\Responses\ApiErrorResponse` — error-конверт `{ errors, meta }`.
- `App\Support\ApiMeta::for($request)` — `{ timestamp, requestId }`.
- `App\Http\Middleware\AddApiMeta` — вливает `meta` в 2xx JSON-ответы (навесить на группу роутов).
- `App\Http\Middleware\SetRequestId` — кладёт `request_id` в атрибуты запроса и заголовок `X-Request-Id`.
- `App\Exceptions\{DomainException, ForbiddenException, NotFoundException, ConflictException}` (+ `::from(ErrorText)`).
- `App\Contracts\ErrorText` — контракт enum-а кодов (`code`/`title`/`detail`); текст в `lang/<locale>/errors.php`.

## Frontend

Парсер `resources/js/lib/http.ts` понимает оба формата ошибок (новый `errors:[]` и
старый `{message, errors:{}}`) и сводит валидацию к `Record<field, string[]>`
(поле берётся из `source.pointer`) — форма (`useZodForm.setServerErrors`) работает без изменений.

Репозитории читают полезную нагрузку из `.data`:

```ts
const { data } = await getJson('/api/user', 'Failed to load user') as { data: AuthUser }
```

## OpenAPI

Success-ответы указывают `data` + `meta`; коллекции — `meta` через `CollectionMeta`
(пагинация + timestamp + requestId), одиночные — `ApiMeta`. Ошибки Users-эндпоинтов
ссылаются на переиспользуемые ответы `*Errors` (`ForbiddenErrors`, `NotFoundErrors`,
`ValidationErrors`, `UnauthorizedErrors`) → `common.yaml#/ApiErrorResponse`.
См. [[add-openapi]].

## Related

- [[jsonapi-conventions]] — query-параметры, `filter[]`/`page[]`, URLSearchParams
- [[add-crud-jsonapi]] — сборка CRUD-эндпоинта целиком
- [[form-validation]] — формы + разбор ошибок валидации на фронте
- [[static-analysis]] — PHPStan level 10
