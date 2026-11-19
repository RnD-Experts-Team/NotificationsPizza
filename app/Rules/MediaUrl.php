<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Accepts either a full absolute URL (http(s)://..., what the 'announcements'
 * disk produces in real environments and what an external GIF provider's CDN
 * link looks like) or a root-relative path (/storage/...). Laravel's built-in
 * 'url' rule rejects the latter, which is what Storage::fake() returns in
 * tests — this keeps the same contract working in both environments instead
 * of the test suite diverging from real disk behavior.
 */
class MediaUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || !preg_match('#^(https?://\S+|/\S+)$#', $value)) {
            $fail('The :attribute must be a valid URL or an absolute path.');
        }
    }
}
