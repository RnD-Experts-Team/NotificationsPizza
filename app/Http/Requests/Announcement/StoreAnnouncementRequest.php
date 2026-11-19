<?php

namespace App\Http\Requests\Announcement;

use App\Rules\MediaUrl;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['nullable', 'string', 'max:50'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'version' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
            'is_pinned' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'media_url' => ['nullable', 'string', 'max:2048', new MediaUrl()],
            'media_type' => ['nullable', 'string', 'in:image,gif,video'],
            'color_start' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_end' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }

    /**
     * A single color (color_start only) means "solid color"; two colors
     * means "gradient". color_end without color_start has no meaning, so
     * it's rejected explicitly rather than silently ignored.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->filled('color_end') && !$this->filled('color_start')) {
                $validator->errors()->add('color_end', 'color_end requires color_start to also be set.');
            }
        });
    }
}