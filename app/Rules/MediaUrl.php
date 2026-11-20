<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Accepts either a full absolute URL (http(s)://..., what an external GIF
 * provider's CDN link looks like) or a disk-relative path such as
 * "media/<uuid>.png" (what our own uploads now store — see
 * AnnouncementMediaService). A leading slash is tolerated too. No "..": the
 * value can end up passed straight to Storage::disk(...)->delete().
 */
class MediaUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || $value === '') {
            $fail('The :attribute must be a valid URL or storage path.');

            return;
        }

        if (str_contains($value, '..')) {
            $fail('The :attribute must be a valid URL or storage path.');

            return;
        }

        if (preg_match('#^https?://\S+$#', $value)) {
            return;
        }

        if (preg_match('#^/?[A-Za-z0-9_\-./]+$#', $value)) {
            return;
        }

        $fail('The :attribute must be a valid URL or storage path.');
    }
}
