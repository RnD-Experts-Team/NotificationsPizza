<?php

namespace App\Services\EventConsume\Handlers;

use App\Services\EmailService;
use App\Services\EventConsume\EventHandlerInterface;

class EmailSendHandler implements EventHandlerInterface
{
    public function __construct(
        private readonly EmailService $service
    ) {
    }

    public function handle(array $event): void
    {
        $template = (string) data_get($event, 'data.template', '');
        $subject = (string) data_get($event, 'data.subject', '');
        $users = data_get($event, 'data.users', []);

        if ($template === '') {
            throw new \Exception('EmailSendHandler: missing data.template');
        }

        if ($subject === '') {
            throw new \Exception('EmailSendHandler: missing data.subject');
        }

        if (!is_array($users) || empty($users)) {
            throw new \Exception('EmailSendHandler: data.users must be a non-empty array');
        }

        $attachments = $this->attachments(data_get($event, 'data.attachments', []));

        foreach ($users as $index => $user) {
            $to = (string) data_get($user, 'email', '');
            $data = data_get($user, 'data', []);

            if ($to === '') {
                throw new \Exception("EmailSendHandler: users[{$index}].email is required");
            }

            if (!is_array($data)) {
                throw new \Exception("EmailSendHandler: users[{$index}].data must be an array");
            }

            $this->service->send(
                template: $template,
                subject: $subject,
                to: $to,
                data: $data,
                attachments: $attachments
            );
        }
    }

    /**
     * @return array<int, array{url: string, name: string, mime: string, size: int}>
     */
    private function attachments(mixed $attachments): array
    {
        if (!is_array($attachments)) {
            throw new \Exception('EmailSendHandler: data.attachments must be an array');
        }

        $result = [];

        foreach ($attachments as $index => $attachment) {
            $url = data_get($attachment, 'url');
            $name = data_get($attachment, 'name');
            $mime = data_get($attachment, 'mime');
            $size = data_get($attachment, 'size');

            if (!is_string($url) || $url === '' || !is_string($name) || $name === '' || !is_string($mime) || $mime === '' || !is_int($size) || $size < 0) {
                throw new \Exception("EmailSendHandler: attachments[{$index}] needs url, name, mime (strings) and size (integer bytes)");
            }

            $result[] = ['url' => $url, 'name' => $name, 'mime' => $mime, 'size' => $size];
        }

        return $result;
    }
}