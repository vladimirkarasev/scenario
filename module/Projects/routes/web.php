<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Module\Projects\Http\Controllers\ProjectWebController;

Route::get('projects', [ProjectWebController::class, 'index'])->name('projects.index');
