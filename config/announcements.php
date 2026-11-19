<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Max video size for announcements/comments media
    |--------------------------------------------------------------------------
    |
    | Enforced WHILE chunks are arriving (see AnnouncementVideoChunkUploadService),
    | not only after the final merge — so an oversized upload is rejected and
    | its temp chunks are deleted as soon as the running total crosses this
    | limit, instead of accepting every chunk of an oversized file first.
    |
    | Raising this limit later is just this one env var — no code change.
    |
    */
    'max_video_bytes' => (int) env('ANNOUNCEMENTS_MAX_VIDEO_BYTES', 100 * 1024 * 1024),

];
