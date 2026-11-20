<?php

namespace App\Services\Announcement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Toggle like/unlike, shared by Announcement and AnnouncementComment
 * (anything using the Likeable concern) — not a multi-reaction system, the
 * manager only asked for a like.
 *
 * A single toggle endpoint, per manager feedback (2026-11-20) — the earlier
 * explicit like()/unlike() (PUT/DELETE) was chosen for retry-safety, but the
 * manager wants one endpoint, so this trades that safety back for it.
 */
class LikeService
{
    public function toggle(Model $likeable, int $userId): array
    {
        return DB::transaction(function () use ($likeable, $userId) {
            $existing = $likeable->likes()->where('user_id', $userId)->first();

            if ($existing) {
                $existing->delete();

                return [
                    'liked' => false,
                    'likes_count' => $likeable->likes()->count(),
                ];
            }

            try {
                $likeable->likes()->create(['user_id' => $userId]);
            } catch (QueryException $e) {
                // Concurrent toggle race: the unique constraint already
                // recorded it, so this one is a no-op — still liked.
            }

            return [
                'liked' => true,
                'likes_count' => $likeable->likes()->count(),
            ];
        });
    }
}
