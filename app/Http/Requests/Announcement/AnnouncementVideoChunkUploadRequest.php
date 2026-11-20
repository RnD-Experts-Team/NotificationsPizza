<?php

namespace App\Http\Requests\Announcement;

use Illuminate\Foundation\Http\FormRequest;

/**
 * upload_uuid and chunk_index come from the route (/video/uploads/{uploadUuid}/chunks/{chunkIndex}),
 * not the body — the uuid is backend-issued by InitAnnouncementVideoUploadRequest,
 * never invented by the frontend. width/height/duration_seconds are whatever
 * the browser's <video> element already knows; send them on any chunk
 * (typically the last one) and they ride along to the finalized media.
 */
class AnnouncementVideoChunkUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'chunk' => ['required', 'file'],
            'width' => ['nullable', 'integer', 'min:1'],
            'height' => ['nullable', 'integer', 'min:1'],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
