<?php

namespace Tests\Feature\EventConsume;

use App\Mail\GenericMailable;
use App\Services\EventConsume\EventRouter;
use App\Services\EventConsume\Handlers\EmailSendHandler;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * notifications.v1.email.send - the report_ready mail and its optional attachments.
 *
 * QUEUE_CONNECTION=sync, so the SendEmailJob (and its downloads) runs inside handle().
 */
class EmailSendHandlerTest extends TestCase
{
    private const URL = 'https://data.example.test/files/report.csv?signature=abc';

    /**
     * @param  array<int, array<string, mixed>>|null  $attachments
     */
    private function handle(?array $attachments): void
    {
        $data = [
            'template' => 'report_ready',
            'subject' => 'Your report is ready',
            'users' => [[
                'email' => 'owner@example.test',
                'data' => [
                    'report_name' => 'Daily sales',
                    'period_label' => 'Last 7 days',
                    'row_count' => 1234,
                    'run_url' => 'https://portal.example.test/dashboard/report-builder/runs/01ABC',
                    'expires_on' => '2026-11-30',
                ],
            ]],
        ];

        if ($attachments !== null) {
            $data['attachments'] = $attachments;
        }

        app(EmailSendHandler::class)->handle(['data' => $data]);
    }

    private function attachment(int $size = 5): array
    {
        return ['url' => self::URL, 'name' => 'daily-sales.csv', 'mime' => 'text/csv', 'size' => $size, 'extra' => 'ignored'];
    }

    private function tempFiles(): array
    {
        return File::glob(storage_path('app/private/mail-attachments/*'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/private/mail-attachments'));

        parent::tearDown();
    }

    public function test_a_mail_without_attachments_still_renders_the_template(): void
    {
        Mail::fake();
        Http::fake();

        $this->handle(null);

        Http::assertNothingSent();
        Mail::assertSent(GenericMailable::class, function (GenericMailable $mail) {
            $mail->assertSeeInHtml('Daily sales');
            $mail->assertSeeInHtml('Last 7 days');
            $mail->assertSeeInHtml('1,234');
            $mail->assertSeeInHtml('https://portal.example.test/dashboard/report-builder/runs/01ABC');
            $mail->assertSeeInHtml('2026-11-30');
            $mail->assertDontSeeInHtml('too large to attach');

            return $mail->hasTo('owner@example.test') && $mail->files === [];
        });
    }

    public function test_the_attachment_is_downloaded_and_attached(): void
    {
        Mail::fake();
        Http::fake([self::URL => Http::response('a,b', 200)]);

        $this->handle([$this->attachment()]);

        Http::assertSentCount(1);
        Mail::assertSent(GenericMailable::class, function (GenericMailable $mail) {
            $mail->assertDontSeeInHtml('too large to attach');

            return $mail->hasTo('owner@example.test')
                && count($mail->files) === 1
                && $mail->files[0]['name'] === 'daily-sales.csv'
                && $mail->hasAttachment(
                    Attachment::fromPath($mail->files[0]['path'])->as('daily-sales.csv')->withMime('text/csv')
                );
        });
    }

    public function test_the_downloaded_bytes_reach_the_outgoing_message(): void
    {
        Http::fake([self::URL => Http::response('a,b', 200)]);

        // The array transport keeps the message by reference, so read it while the mail is going out.
        $sent = [];
        Event::listen(MessageSent::class, function (MessageSent $event) use (&$sent) {
            foreach ($event->message->getAttachments() as $part) {
                $sent[] = [
                    $part->getPreparedHeaders()->getHeaderParameter('Content-Disposition', 'filename'),
                    $part->getMediaType() . '/' . $part->getMediaSubtype(),
                    $part->getBody(),
                ];
            }
        });

        $this->handle([$this->attachment()]);

        $this->assertSame([['daily-sales.csv', 'text/csv', 'a,b']], $sent);
    }

    public function test_a_declared_size_over_15_mb_is_skipped_without_downloading(): void
    {
        Mail::fake();
        Http::fake();

        $this->handle([$this->attachment(20 * 1024 * 1024)]);

        Http::assertNothingSent();
        Mail::assertSent(GenericMailable::class, function (GenericMailable $mail) {
            $mail->assertSeeInHtml('The file was too large to attach; open the report in the portal.');

            return $mail->files === [] && $mail->data['attachment_skipped'] === true;
        });
    }

    public function test_a_download_larger_than_declared_is_skipped(): void
    {
        Mail::fake();
        Http::fake([self::URL => Http::response(str_repeat('x', 15 * 1024 * 1024 + 1), 200)]);

        $this->handle([$this->attachment()]);

        Mail::assertSent(GenericMailable::class, function (GenericMailable $mail) {
            $mail->assertSeeInHtml('The file was too large to attach');

            return $mail->files === [];
        });
        $this->assertSame([], $this->tempFiles());
    }

    public function test_a_failed_download_is_skipped_and_the_mail_still_goes_out(): void
    {
        Mail::fake();
        Http::fake([self::URL => Http::response('expired', 403)]);

        $this->handle([$this->attachment()]);

        Mail::assertSent(GenericMailable::class, function (GenericMailable $mail) {
            $mail->assertSeeInHtml('The file was too large to attach');

            return $mail->files === [];
        });
        $this->assertSame([], $this->tempFiles());
    }

    public function test_temp_files_are_removed_after_sending(): void
    {
        Mail::fake();
        Http::fake([self::URL => Http::response('a,b', 200)]);

        $this->handle([$this->attachment()]);

        Mail::assertSent(GenericMailable::class);
        $this->assertSame([], $this->tempFiles());
    }

    public function test_temp_files_are_removed_when_sending_fails(): void
    {
        Http::fake([self::URL => Http::response('a,b', 200)]);
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp down'));

        try {
            $this->handle([$this->attachment()]);
            $this->fail('the send failure should surface so the queue retries');
        } catch (\RuntimeException $e) {
            $this->assertSame('smtp down', $e->getMessage());
        }

        $this->assertSame([], $this->tempFiles());
    }

    public function test_every_recipient_gets_the_attachment(): void
    {
        Mail::fake();
        Http::fake([self::URL => Http::response('a,b', 200)]);

        app(EmailSendHandler::class)->handle(['data' => [
            'template' => 'report_ready',
            'subject' => 'Your report is ready',
            'users' => [
                ['email' => 'a@example.test', 'data' => $this->templateData()],
                ['email' => 'b@example.test', 'data' => $this->templateData()],
            ],
            'attachments' => [$this->attachment()],
        ]]);

        Mail::assertSent(GenericMailable::class, 2);
        $this->assertSame([], $this->tempFiles());
    }

    public function test_a_malformed_attachment_is_rejected(): void
    {
        $this->expectExceptionMessage('attachments[0] needs url, name, mime (strings) and size (integer bytes)');

        $this->handle([['url' => self::URL, 'name' => 'daily-sales.csv']]);
    }

    public function test_attachments_must_be_a_list(): void
    {
        $this->expectExceptionMessage('data.attachments must be an array');

        app(EmailSendHandler::class)->handle(['data' => [
            'template' => 'report_ready',
            'subject' => 'Your report is ready',
            'users' => [['email' => 'a@example.test', 'data' => []]],
            'attachments' => 'nope',
        ]]);
    }

    public function test_the_router_still_resolves_email_send(): void
    {
        $this->assertSame(
            EmailSendHandler::class,
            app(EventRouter::class)->resolve('notifications.v1.email.send'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function templateData(): array
    {
        return [
            'report_name' => 'Daily sales',
            'period_label' => 'Last 7 days',
            'row_count' => 1,
            'run_url' => 'https://portal.example.test/runs/1',
            'expires_on' => '2026-11-30',
        ];
    }
}
