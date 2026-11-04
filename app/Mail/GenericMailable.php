<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;

class GenericMailable extends Mailable
{
    /**
     * @param  array<int, array{path: string, name: string, mime: string}>  $files
     */
    public function __construct(
        public string $template,
        public string $subjectLine,
        public array $data,
        public array $files = []
    ) {
    }

    public function build()
    {
        return $this->subject($this->subjectLine)
            ->view("emails.templates.{$this->template}")
            ->with($this->data);
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return array_map(
            fn (array $file) => Attachment::fromPath($file['path'])
                ->as($file['name'])
                ->withMime($file['mime']),
            $this->files
        );
    }
}
