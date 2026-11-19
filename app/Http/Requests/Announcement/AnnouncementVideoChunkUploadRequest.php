<?php

namespace App\Http\Requests\Announcement;

use Illuminate\Foundation\Http\FormRequest;

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
            'upload_uuid' => ['required', 'uuid'],
            'chunk_index' => ['required', 'integer', 'min:0'],
            'total_chunks' => ['required', 'integer', 'min:1'],
            'original_filename' => ['required_if:chunk_index,0', 'nullable', 'string', 'max:255'],
            // The whole file's byte size, declared once on the first chunk —
            // lets the server reject an oversized upload before accepting
            // (and storing) even the first chunk, instead of only catching it
            // once enough chunks have arrived to cross the limit.
            'total_size' => ['required_if:chunk_index,0', 'nullable', 'integer', 'min:1'],
        ];
    }
}
