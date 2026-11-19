<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Announcement extends Model
{
    use Concerns\Likeable;

    protected $fillable = [
        'type',
        'title',
        'body',
        'version',
        'is_active',
        'starts_at',
        'ends_at',
        'is_pinned',
        'media_url',
        'media_type',
        'color_start',
        'color_end',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_pinned' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function userStates(): HasMany
    {
        return $this->hasMany(AnnouncementUserState::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(AnnouncementComment::class);
    }
}