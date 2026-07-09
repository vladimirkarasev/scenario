<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Module\Groups\Http\Controllers\GroupMembersController;
use Module\Groups\Http\Controllers\UserGroupsController;

Route::middleware('can:group_create')
    ->get('groups/{group}/member-candidates', [GroupMembersController::class, 'candidates']);

Route::middleware('can:group_view')->group(static function (): void {
    Route::get('groups', [UserGroupsController::class, 'index']);
    Route::get('groups/{group}', [UserGroupsController::class, 'show']);
    Route::get('groups/{group}/members', [GroupMembersController::class, 'index']);
});

Route::middleware('can:group_create')->group(static function (): void {
    Route::post('groups', [UserGroupsController::class, 'store']);
    Route::put('groups/{group}', [UserGroupsController::class, 'update']);
    Route::patch('groups/{group}', [UserGroupsController::class, 'update']);
    Route::post('groups/{group}/members', [GroupMembersController::class, 'store']);
    Route::delete('groups/{group}/members/{user}', [GroupMembersController::class, 'destroy']);
});

Route::middleware('can:group_delete')->group(static function (): void {
    Route::delete('groups/{group}', [UserGroupsController::class, 'destroy']);
});
