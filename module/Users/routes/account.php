<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Module\Users\Http\Controllers\CurrentUserController;

// Данные текущего пользователя. Не требует контекста проекта (в отличие от api.php).
Route::get('user', CurrentUserController::class);
