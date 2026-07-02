<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Источник текста ошибки: машинный код + человекочитаемые title/detail.
 * Реализуется enum-ами кодов ошибок каждого модуля. Единственная точка,
 * которую нужно будет заменить на `__()` при подключении локализации.
 */
interface ErrorText
{
    public function code(): string;

    public function title(): string;

    public function detail(): string;
}
