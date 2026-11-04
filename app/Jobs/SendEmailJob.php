<?php

namespace App\Jobs;

use App\Mail\GenericMailable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SendEmailJob implements ShouldQueue
{
    use Queueable;

    private const MAX_ATTACHMENT_BYTES = 15 * 1024 * 1024;

    /**
     * @param  array<int, array{url: string, name: string, mime: string, size: int}>  $attachments
     */
    public function __construct(
        public string $template,
        public string $subject,
        public string $to,
        public array $data,
        public array $attachments = []
    ) {
    }

    public function handle(): void
    {
        $data = $this->data;
        $files = [];

        try {
            foreach ($this->attachments as $attachment) {
                $path = storage_path('app/private/mail-attachments/' . Str::ulid());

                // Registered before the download so a half-written file is cleaned up too.
                $files[] = ['path' => $path, 'name' => $attachment['name'], 'mime' => $attachment['mime']];

                if (!$this->download($attachment, $path)) {
                    array_pop($files);
                    File::delete($path);
                    $data['attachment_skipped'] = true;
                }
            }

            Mail::to($this->to)
                ->send(new GenericMailable($this->template, $this->subject, $data, $files));
        } finally {
            foreach ($files as $file) {
                File::delete($file['path']);
            }
        }
    }

    /**
     * Streams the attachment to disk. False when it was too large or could not be fetched.
     *
     * @param  array{url: string, name: string, mime: string, size: int}  $attachment
     */
    private function download(array $attachment, string $path): bool
    {
        if ($attachment['size'] > self::MAX_ATTACHMENT_BYTES) {
            return false;
        }

        File::ensureDirectoryExists(dirname($path));

        try {
            Http::timeout(60)
                ->withOptions([
                    'sink' => $path,
                    'progress' => function ($downloadTotal, $downloaded) {
                        if ($downloaded > self::MAX_ATTACHMENT_BYTES) {
                            throw new \RuntimeException('attachment exceeds the size limit');
                        }
                    },
                ])
                ->get($attachment['url'])
                ->throw();
        } catch (\Throwable $e) {
            Log::warning('SendEmailJob: attachment skipped', ['name' => $attachment['name'], 'error' => $e::class]);

            return false;
        }

        return filesize($path) <= self::MAX_ATTACHMENT_BYTES;
    }
}
