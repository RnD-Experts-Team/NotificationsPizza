<?php

namespace App\Services\Announcement;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AnnouncementMediaService
{
    private const DISK = 'announcements';

    /**
     * Direct, single-request upload for a self-made image or GIF (small
     * files only). GIFs picked from the provider library go through
     * GifProviderService instead and never touch this method.
     */
    public function uploadImage(UploadedFile $file): array
    {
        $extension = $file->getClientOriginalExtension() ?: $file->extension();
        $filename = Str::uuid()->toString().'.'.$extension;

        Storage::disk(self::DISK)->putFileAs('media', $file, $filename);

        return [
            'media_url' => $this->toUrl('media/'.$filename),
            'media_type' => $file->getMimeType() === 'image/gif' ? 'gif' : 'image',
        ];
    }

    /**
     * Builds the public URL from config directly rather than via
     * Storage::disk(...)->url() — Storage::fake() swaps the disk's URL
     * generator too, which would make the "is this our own file?" check in
     * deleteByUrl() unreliable in tests. Using config keeps it identical in
     * both environments.
     */
    public function toUrl(string $relativePath): string
    {
        return rtrim((string) config('filesystems.disks.'.self::DISK.'.url'), '/').'/'.ltrim($relativePath, '/');
    }

    /**
     * Deletes a previously stored announcement media file given its public
     * URL. Silently does nothing when the URL does not belong to our own
     * 'announcements' disk — e.g. a GIF picked from an external provider's
     * CDN, which this project never downloads or owns.
     */
    public function deleteByUrl(?string $mediaUrl): void
    {
        if (!$mediaUrl) {
            return;
        }

        $base = rtrim((string) config('filesystems.disks.'.self::DISK.'.url'), '/');

        if ($base === '' || !str_starts_with($mediaUrl, $base.'/')) {
            return;
        }

        $relativePath = ltrim(substr($mediaUrl, strlen($base)), '/');

        Storage::disk(self::DISK)->delete($relativePath);
    }
}
