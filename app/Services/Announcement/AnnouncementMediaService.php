<?php

namespace App\Services\Announcement;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Uses the existing 'public' disk rather than a dedicated one — no new
 * filesystem config needed, just a subfolder within it.
 */
class AnnouncementMediaService
{
    private const DISK = 'public';
    private const PREFIX = 'announcements/media';

    /**
     * Direct, single-request upload for a self-made image or GIF (small
     * files only). GIFs picked from the provider library go through
     * GifProviderService instead and never touch this method.
     *
     * Returns both the relative `media_path` (what the frontend must send
     * back when creating/updating the announcement or comment) and a
     * `media_url` (for immediate preview only — never resubmitted as-is).
     */
    public function uploadImage(UploadedFile $file): array
    {
        $extension = $file->getClientOriginalExtension() ?: $file->extension();
        $filename = Str::uuid()->toString().'.'.$extension;
        $relativePath = self::PREFIX.'/'.$filename;

        Storage::disk(self::DISK)->putFileAs(self::PREFIX, $file, $filename);

        return [
            'media_path' => $relativePath,
            'media_url' => $this->toUrl($relativePath),
            'media_type' => $file->getMimeType() === 'image/gif' ? 'gif' : 'image',
        ];
    }

    /**
     * Builds the public URL from config directly rather than via
     * Storage::disk(...)->url() — Storage::fake() swaps the disk's URL
     * generator too, which would make this unreliable in tests. Using
     * config keeps it identical in both environments.
     */
    public function toUrl(string $relativePath): string
    {
        return rtrim((string) config('filesystems.disks.'.self::DISK.'.url'), '/').'/'.ltrim($relativePath, '/');
    }

    public function isExternal(string $value): bool
    {
        return (bool) preg_match('#^https?://#', $value);
    }

    /**
     * What gets rendered to the frontend: an external GIF-provider URL is
     * already absolute and passed through unchanged; our own upload is
     * stored as a disk-relative path and turned into a real URL on the fly,
     * so moving the app to a new host/APP_URL never invalidates a value
     * that was already saved (unlike storing the absolute URL itself).
     */
    public function toPublicUrl(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return $this->isExternal($value) ? $value : $this->toUrl($value);
    }

    /**
     * Deletes a previously stored announcement media file given its stored
     * path value. No-ops for an external GIF-provider URL, which this
     * project never downloads or owns.
     */
    public function deleteByPath(?string $value): void
    {
        if (!$value || $this->isExternal($value)) {
            return;
        }

        Storage::disk(self::DISK)->delete(ltrim($value, '/'));
    }
}
