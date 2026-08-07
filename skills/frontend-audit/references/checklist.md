# Frontend audit checklist

Использовать как маршрут поиска, а не как механическую гарантию качества.

## Architecture

```bash
rg -n "axios|fetch\(|\.get\(|\.post\(|\.put\(|\.patch\(|\.delete\(" resources/js/modules
rg -n "z\.object|z\.discriminatedUnion|z\.setErrorMap" resources/js/modules
rg -n "export (interface|type)" resources/js/modules/*/repositories
rg -n "\bany\b|as any|@ts-ignore|@ts-expect-error" resources/js
```

Проверить каждое совпадение в контексте: имя метода само по себе не доказывает HTTP-вызов.

## Concurrency and lifecycle

```bash
rg -n "watch\(|watchEffect\(|onMounted\(|setTimeout\(|debounce|Promise\.all" resources/js/modules
rg -n "load[A-Z]|fetch[A-Z]|reload|refresh" resources/js/modules/*/composables
```

Для каждого async read проверить порядок ответов, unmount и обработку ошибок. Для mutations проверить сериализацию и повторную отправку.

## Security

```bash
rg -n "v-html|innerHTML|outerHTML|target=.?_blank|localStorage|sessionStorage" resources/js
rg -n "VITE_|access[_-]?token|refresh[_-]?token|Authorization" resources/js .env.example vite.config.*
rg -n "FileReader|arrayBuffer|readAs|xlsx|spreadsheet|csv" resources/js
npm audit --omit=dev
```

Проверить sanitizer allowlist, удаление tokens из URL, ограничения импорта и происхождение HTML.

## Design and decomposition

Сопоставить sibling pages по:

- shell, header, content width и spacing;
- tabs/navigation;
- search, filters, pagination;
- loading, empty и error states;
- destructive actions и permission visibility;
- light/dark semantic colors.

## Verification

```bash
npm run test:run
npx vue-tsc --noEmit
npx eslint resources/js --quiet
npm run build
git diff --check
```

Добавить `npm run knip` и полный ESLint, когда они есть в проекте и не блокируются известным baseline.
