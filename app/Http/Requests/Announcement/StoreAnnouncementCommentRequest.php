<?php

namespace App\Http\Requests\Announcement;

use App\Rules\MediaUrl;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreAnnouncementCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // TBD with the manager: no max length was given for comments.
            'body' => ['nullable', 'string', 'max:2000'],
            'media_url' => ['nullable', 'string', 'max:2048', new MediaUrl()],
            // No video in comments — the manager only asked for image/GIF here,
            // unlike the announcement itself which also allows video.
            'media_type' => ['nullable', 'string', 'in:image,gif', 'required_with:media_url'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (!$this->filled('body') && !$this->filled('media_url')) {
                $validator->errors()->add('body', 'A comment needs either text or media.');
            }
        });
    }
}
