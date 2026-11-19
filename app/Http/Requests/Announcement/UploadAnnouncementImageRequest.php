<?php

namespace App\Http\Requests\Announcement;

use Illuminate\Foundation\Http\FormRequest;

class UploadAnnouncementImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // TBD with the manager: no size cap was given for images/GIFs, only
            // for video. 10 MB is a safe default until that's confirmed.
            'media' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:10240'],
        ];
    }
}
