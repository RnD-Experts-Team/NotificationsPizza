<?php

namespace App\Http\Requests\Announcement;

use App\Rules\MediaUrl;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['sometimes', 'nullable', 'string', 'max:50'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'body' => ['sometimes', 'required', 'string'],
            'version' => ['sometimes', 'nullable', 'string', 'max:50'],
            'is_active' => ['sometimes', 'boolean'],
            'is_pinned' => ['sometimes', 'boolean'],
            'starts_at' => ['sometimes', 'nullable', 'date'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after_or_equal:starts_at'],
            'media_path' => ['sometimes', 'nullable', 'string', new MediaUrl()],
            'media_type' => ['sometimes', 'nullable', 'string', 'in:image,gif,video'],
            'media_width' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'media_height' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'media_duration_seconds' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'color_start' => ['sometimes', 'nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'color_end' => ['sometimes', 'nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->filled('color_end') && !$this->filled('color_start')) {
                $validator->errors()->add('color_end', 'color_end requires color_start to also be set.');
            }
        });
    }
}