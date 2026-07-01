<?php

use App\Http\Controllers\Dev\IframeAuthWebController;
use App\Http\Controllers\Dev\LoginWebController;
use Illuminate\Support\Facades\Route;

Route::get('/openapi.yaml', static function () {
    return response()->file(public_path('openapi.yaml'), [
        'Content-Type' => 'application/yaml',
        'Cache-Control' => 'no-store, no-cache, must-revalidate',
        'Pragma' => 'no-cache',
    ]);
});

Route::view('/swagger', 'swagger')->name('swagger');
Route::redirect('/scalar', '/swagger', 301);

Route::get('/actions', static fn () => inertia('Actions/Index'))->name('actions');
Route::get('/actions/runs', static fn () => inertia('Actions/Runs'))->name('actions.runs');
Route::get('/actions/schedules', static fn () => inertia('Actions/Schedules'))->name('actions.schedules');

if (config('iframe_auth.dev_enabled')) {
    Route::get('/login', LoginWebController::class)->name('login');
    Route::get('/auth', IframeAuthWebController::class)->name('auth.page');
}
