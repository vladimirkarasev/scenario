<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Privatization\Rector\Class_\FinalizeTestCaseClassRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
        __DIR__.'/module',
        __DIR__.'/tests',
    ])
    ->withSkip([
        // Сгенерированный/инфраструктурный код не трогаем.
        __DIR__.'/bootstrap/cache',
        __DIR__.'/storage',
    ])
    // Аккуратно подтягивает FQCN в use-импорты (как привык проект).
    ->withImportNames(importShortClasses: false, removeUnusedImports: true)
    // Правила миграции под текущую версию PHP из composer.json.
    ->withPhpSets()
    // Безопасные базовые наборы; уровни можно повышать постепенно.
    ->withDeadCodeLevel(0)
    ->withCodeQualityLevel(0)
    ->withRules([
        FinalizeTestCaseClassRector::class
    ]);
