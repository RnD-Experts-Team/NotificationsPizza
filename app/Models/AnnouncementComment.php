<?php

namespace App\Models;

use App\Services\Announcement\AnnouncementMediaService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnouncementComment extends Model
{
    use Concerns\Likeable;

    protected $fillable = [
        'announcement_id',
        'user_id',
        'body',
        'media_path',
        'media_type',
    ];

    protected $hidden = [
        'media_path',
    ];

    protected $appends = [
        'media_url',
    ];

    public function getMediaUrlAttribute(): ?string
    {
        return app(AnnouncementMediaService::class)->toPublicUrl($this->media_path);
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
