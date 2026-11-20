<?php

use App\Http\Controllers\AnnouncementCommentController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AnnouncementMediaController;
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
    // Unified calendar feed. Stores are in the query (stores=a,b): the pizzasys
    // rule must read store_id_sources.query ["stores"] with policy "all".
    Route::get('/calendar/feed', \App\Http\Controllers\CalendarFeedController::class);

    // Media: self-upload (image/GIF), the Giphy picker (trending/search), and
    // chunked video upload. Shared by the announcement itself and by comments
    // below — see docs/IMPLEMENTATION_TASKS_ANNOUNCEMENTS_MEDIA.md Phase 3-5.
    Route::prefix('media')->group(function () {
        Route::post('/image', [AnnouncementMediaController::class, 'uploadImage']);
        Route::get('/gifs/trending', [AnnouncementMediaController::class, 'gifsTrending'])->withoutMiddleware('throttle:api')->middleware('throttle:api-poll');
        Route::get('/gifs/search', [AnnouncementMediaController::class, 'gifsSearch'])->withoutMiddleware('throttle:api')->middleware('throttle:api-poll');
        // The upload uuid is backend-issued by initVideoUpload, never sent
        // by the frontend — see AnnouncementVideoChunkUploadService.
        Route::post('/video/uploads', [AnnouncementMediaController::class, 'initVideoUpload']);
        Route::post('/video/uploads/{uploadUuid}/chunks/{chunkIndex}', [AnnouncementMediaController::class, 'uploadVideoChunk'])
            ->whereUuid('uploadUuid')->whereNumber('chunkIndex');
        Route::delete('/video/uploads/{uploadUuid}', [AnnouncementMediaController::class, 'revertVideoChunk'])
            ->whereUuid('uploadUuid');
    });

    // Comments (Phase 10) and likes (Phase 11) on a comment. A single toggle
    // endpoint for like — see Likeable.
    Route::delete('/announcement/comments/{comment}', [AnnouncementCommentController::class, 'destroy']);
    Route::post('/announcement/comments/{comment}/like', [AnnouncementCommentController::class, 'toggleLike']);

    Route::get('/{announcement}', [AnnouncementController::class, 'show']);
    Route::put('/{announcement}', [AnnouncementController::class, 'update']);
    Route::delete('/{announcement}', [AnnouncementController::class, 'destroy']);
    Route::post('/{announcement}/like', [AnnouncementController::class, 'toggleLike']);
    Route::get('/{announcement}/comments', [AnnouncementCommentController::class, 'index']);
    Route::post('/{announcement}/comments', [AnnouncementCommentController::class, 'store']);
});

Route::middleware(['throttle:api', 'auth.token.store'])->prefix('notifications')->group(function () {
    Route::get('/', [NotificationController::class, 'index']);
    Route::get('/unread', [NotificationController::class, 'unread'])->withoutMiddleware('throttle:api')->middleware('throttle:api-poll');
    Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
});