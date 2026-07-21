<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Module\Users\Http\Controllers\CurrentUserController;

Route::get('user', CurrentUserController::class);
