<?php

namespace App\Models;

use App\Services\Announcement\AnnouncementMediaService;
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
        'media_path',
        'media_type',
        'media_width',
        'media_height',
        'media_duration_seconds',
        'color_start',
        'color_end',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_pinned' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'media_width' => 'integer',
        'media_height' => 'integer',
        'media_duration_seconds' => 'integer',
    ];

    protected $hidden = [
        'media_path',
    ];

    protected $appends = [
        'media_url',
    ];

    /**
     * Computed from the stored `media_path` (a disk-relative path for our
     * own uploads, or an external GIF-provider URL passed through as-is) —
     * see AnnouncementMediaService::toPublicUrl(). Keeps the public API
     * contract ("media_url") while storing something that survives an
     * APP_URL/host change.
     */
    public function getMediaUrlAttribute(): ?string
    {
        return app(AnnouncementMediaService::class)->toPublicUrl($this->media_path);
    }

    public function userStates(): HasMany
    {
        return $this->hasMany(AnnouncementUserState::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(AnnouncementComment::class);
    }
}
