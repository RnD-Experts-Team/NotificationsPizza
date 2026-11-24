<?php

namespace App\Http\Requests\Announcement;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Starts a chunked video upload: the backend generates and owns the
 * upload_uuid (see AnnouncementVideoChunkUploadService::initUpload) and the
 * declared total_size is checked against the limit here, before a single
 * chunk is accepted.
 */
class InitAnnouncementVideoUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'total_chunks' => ['required', 'integer', 'min:1'],
            'total_size' => ['required', 'integer', 'min:1'],
            'original_filename' => ['required', 'string', 'max:255'],
        ];
    }
}
