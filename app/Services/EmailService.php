<?php

namespace App\Services;

use App\Jobs\SendEmailJob;

class EmailService
{
    /**
     * @param  array<int, array{url: string, name: string, mime: string, size: int}>  $attachments
     */
    public function send(string $template, string $subject, string $to, array $data, array $attachments = []): void
    {
        dispatch(new SendEmailJob($template, $subject, $to, $data, $attachments));
    }
}
