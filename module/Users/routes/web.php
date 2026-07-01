<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Module\Users\Http\Controllers\UserWebController;

Route::middleware('can:user_view')
    ->get('users', [UserWebController::class, 'index'])
    ->name('users.index');

Route::middleware('can:role_view')
    ->get('users/roles', [UserWebController::class, 'roles'])
    ->name('users.roles');

Route::middleware('can:group_view')
    ->get('users/groups', [UserWebController::class, 'groups'])
    ->name('users.groups');
