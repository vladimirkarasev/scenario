<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Scenario API — Swagger UI</title>
    @vite('resources/js/swagger.ts')
</head>
<body>
    <div id="swagger-ui"></div>
</body>
</html>
