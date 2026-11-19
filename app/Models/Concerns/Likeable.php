<?php

namespace App\Models\Concerns;

use App\Models\AnnouncementLike;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait Likeable
{
    public function likes(): MorphMany
    {
        return $this->morphMany(AnnouncementLike::class, 'likeable');
    }
}
