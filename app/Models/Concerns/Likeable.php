<?php

namespace App\Models\Concerns;

use App\Models\AnnouncementLike;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

trait Likeable
{
    public function likes(): MorphMany
    {
        return $this->morphMany(AnnouncementLike::class, 'likeable');
    }

    /**
     * A single toggle endpoint, per manager feedback — see routes/api.php.
     */
    public function toggleLike(int $userId): array
    {
        return DB::transaction(function () use ($userId) {
            $existing = $this->likes()->where('user_id', $userId)->first();

            if ($existing) {
                $existing->delete();

                return [
                    'liked' => false,
                    'likes_count' => $this->likes()->count(),
                ];
            }

            try {
                $this->likes()->create(['user_id' => $userId]);
            } catch (QueryException $e) {
                // Concurrent toggle race: the unique constraint already
                // recorded it, so this one is a no-op — still liked.
            }

            return [
                'liked' => true,
                'likes_count' => $this->likes()->count(),
            ];
        });
    }
}
