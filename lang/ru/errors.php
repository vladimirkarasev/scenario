<?php

declare(strict_types=1);

/*
 * Тексты доменных ошибок. Ключ — машинный код (см. *ErrorCode enum модуля):
 * errors.<module>.<CODE>.{title|detail}. Enum кодов читает их через trans().
 */

return [
    'users' => [
        'USER_NOT_FOUND' => [
            'title' => 'Пользователь не найден',
            'detail' => 'Пользователь не найден в проекте.',
        ],
        'SYSTEM_USER_IMMUTABLE' => [
            'title' => 'Доступ запрещён',
            'detail' => 'Системного пользователя нельзя изменить или удалить.',
        ],
        'SELF_DELETE_FORBIDDEN' => [
            'title' => 'Некорректный запрос',
            'detail' => 'Нельзя удалить текущего пользователя.',
        ],
        'LAST_ADMINISTRATOR' => [
            'title' => 'Некорректный запрос',
            'detail' => 'В проекте должен остаться хотя бы один администратор.',
        ],
        'NO_PROJECT_CONTEXT' => [
            'title' => 'Доступ запрещён',
            'detail' => 'Контекст проекта не определён.',
        ],
        'ROLE_NOT_FOUND' => [
            'title' => 'Роль не найдена',
            'detail' => 'Роль не найдена.',
        ],
        'SYSTEM_ROLE_IMMUTABLE' => [
            'title' => 'Доступ запрещён',
            'detail' => 'Системную роль нельзя изменить или удалить.',
        ],
        'ROLE_IN_USE' => [
            'title' => 'Некорректный запрос',
            'detail' => 'Нельзя удалить роль, которая назначена пользователям.',
        ],
        'MANAGE_ADMIN_FORBIDDEN' => [
            'title' => 'Доступ запрещён',
            'detail' => 'Нельзя управлять администратором проекта.',
        ],
        'MANAGE_PRIVILEGED_FORBIDDEN' => [
            'title' => 'Доступ запрещён',
            'detail' => 'Нельзя управлять более привилегированным пользователем.',
        ],
        'ROLE_ASSIGN_FORBIDDEN' => [
            'title' => 'Доступ запрещён',
            'detail' => 'Недостаточно прав для назначения ролей.',
        ],
        'ROLE_ESCALATION_FORBIDDEN' => [
            'title' => 'Доступ запрещён',
            'detail' => 'Нельзя назначить роль выше собственных полномочий.',
        ],
        'PERMISSION_ESCALATION_FORBIDDEN' => [
            'title' => 'Доступ запрещён',
            'detail' => 'Нельзя выдать разрешения выше собственных полномочий.',
        ],
        'ROLE_MANAGE_FORBIDDEN' => [
            'title' => 'Доступ запрещён',
            'detail' => 'Нельзя изменять роль выше собственных полномочий.',
        ],
    ],

    'groups' => [
        'GROUP_NOT_FOUND' => [
            'title' => 'Группа не найдена',
            'detail' => 'Группа не найдена в текущем проекте.',
        ],
        'GROUP_EXTERNAL_ID_CONFLICT' => [
            'title' => 'Внешний ID уже используется',
            'detail' => 'В текущем проекте уже существует другая группа с таким внешним ID.',
        ],
        'GROUP_MEMBER_USER_NOT_FOUND' => [
            'title' => 'Пользователь не найден',
            'detail' => 'Пользователь для добавления в группу не найден.',
        ],
    ],

    'scenario' => [
        'SCENARIO_NOT_FOUND' => [
            'title' => 'Сценарий не найден',
            'detail' => 'Сценарий не найден.',
        ],
        'SCENARIO_VERSION_NOT_FOUND' => [
            'title' => 'Версия сценария не найдена',
            'detail' => 'Версия сценария не найдена.',
        ],
        'SCENARIO_RUN_NOT_FOUND' => [
            'title' => 'Прогон не найден',
            'detail' => 'Прогон сценария не найден.',
        ],
        'DISPATCH_NO_PROJECT_CONTEXT' => [
            'title' => 'Проект не определён',
            'detail' => 'Сервисный пользователь не привязан к проекту.',
        ],
        'DISPATCH_TARGET_USER_NOT_FOUND' => [
            'title' => 'Пользователь не найден',
            'detail' => 'Целевой пользователь не найден в проекте.',
        ],
        'DISPATCH_SCENARIO_NOT_FOUND' => [
            'title' => 'Сценарий не найден',
            'detail' => 'Активный сценарий с указанным тегом не найден.',
        ],
        'SCENARIO_SYSTEM_CATEGORY_DELETE_FORBIDDEN' => [
            'title' => 'Удаление запрещено',
            'detail' => 'Системную папку нельзя удалить.',
        ],
    ],

    'proxy' => [
        'PROXY_ENDPOINT_NOT_FOUND' => [
            'title' => 'Интеграция не найдена',
            'detail' => 'Интеграция не найдена в текущем проекте.',
        ],
        'PROXY_CONNECTION_NOT_FOUND' => [
            'title' => 'Доступ не найден',
            'detail' => 'Доступ не найден в текущем проекте.',
        ],
        'PROXY_REQUEST_NOT_FOUND' => [
            'title' => 'Запрос не найден',
            'detail' => 'Запрос интеграции не найден в текущем проекте.',
        ],
        'PROXY_CATEGORY_NOT_FOUND' => [
            'title' => 'Раздел не найден',
            'detail' => 'Раздел интеграций не найден в текущем проекте.',
        ],
        'PROXY_SYSTEM_CATEGORY_DELETE_FORBIDDEN' => [
            'title' => 'Удаление запрещено',
            'detail' => 'Системный раздел интеграций нельзя удалить.',
        ],
    ],
];
