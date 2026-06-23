<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Module\Users\Http\Controllers\UserWebController;

Route::get('users', [UserWebController::class, 'index'])->name('users.index');
Route::get('users/roles', [UserWebController::class, 'roles'])->name('users.roles');
Route::get('users/groups', [UserWebController::class, 'groups'])->name('users.groups');
