<?php

namespace App\Services\Announcement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent like/unlike, shared by Announcement and AnnouncementComment
 * (anything using the Likeable concern) — not a multi-reaction system, the
 * manager only asked for a like.
 *
 * Deliberately two explicit methods (PUT like / DELETE unlike) instead of a
 * single toggle: a toggle isn't safe to retry — a request that actually
 * succeeded but whose response was lost to a flaky connection, then retried
 * by the client, would flip the state back to the opposite of what the user
 * intended. like()/unlike() always converge to the same end state no matter
 * how many times (or how concurrently) they're called.
 */
class LikeService
{
    public function like(Model $likeable, int $userId): array
    {
        return DB::transaction(function () use ($likeable, $userId) {
            $exists = $likeable->likes()->where('user_id', $userId)->exists();

            if (!$exists) {
                try {
                    $likeable->likes()->create(['user_id' => $userId]);
                } catch (QueryException $e) {
                    // Concurrent like() race: the unique constraint already
                    // recorded it, so this one is a no-op — still liked.
                }
            }

            return [
                'liked' => true,
                'likes_count' => $likeable->likes()->count(),
            ];
        });
    }

    public function unlike(Model $likeable, int $userId): array
    {
        return DB::transaction(function () use ($likeable, $userId) {
            $likeable->likes()->where('user_id', $userId)->delete();

            return [
                'liked' => false,
                'likes_count' => $likeable->likes()->count(),
            ];
        });
    }
}
