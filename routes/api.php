<?php

use App\Http\Controllers\AnnouncementController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NotificationController;

// Rate limits (AppServiceProvider): `throttle:api` covers every route, ahead of
// the pizzasys call. The pollers REPLACE it with `throttle:api-poll`, since the
// general read limit is lower and would cap them.
Route::middleware(['throttle:api', 'auth.token.store'])->prefix('announcements')->group(function () {
    Route::get('/', [AnnouncementController::class, 'index']);
    Route::post('/', [AnnouncementController::class, 'store']);
    Route::get('/visible', [AnnouncementController::class, 'visible'])->withoutMiddleware('throttle:api')->middleware('throttle:api-poll');
    Route::get('/unseen', [AnnouncementController::class, 'unseen'])->withoutMiddleware('throttle:api')->middleware('throttle:api-poll');
    Route::post('/mark-seen', [AnnouncementController::class, 'markSeen']);
    Route::get('/{announcement}', [AnnouncementController::class, 'show']);
    Route::put('/{announcement}', [AnnouncementController::class, 'update']);
    Route::delete('/{announcement}', [AnnouncementController::class, 'destroy']);
});

Route::middleware(['throttle:api', 'auth.token.store'])->prefix('notifications')->group(function () {
    Route::get('/', [NotificationController::class, 'index']);
    Route::get('/unread', [NotificationController::class, 'unread'])->withoutMiddleware('throttle:api')->middleware('throttle:api-poll');
    Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
});