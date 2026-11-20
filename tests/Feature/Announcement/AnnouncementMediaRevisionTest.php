<?php

namespace Tests\Feature\Announcement;

use App\Models\Announcement;
use App\Models\AnnouncementComment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\FakesAuthServer;
use Tests\TestCase;

/**
 * Covers manager feedback round 2 (2026-11-20): like is a single toggle
 * again, media is stored as a disk-relative path (not a full URL), the
 * video chunk-upload uuid is issued by the backend, and video
 * width/height/duration are round-tripped.
 */
class AnnouncementMediaRevisionTest extends TestCase
{
    use FakesAuthServer, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeAuthServer();
        Storage::fake('public');
        Storage::fake('local');
    }

    private function realSizedChunk(string $bytes): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'chunk');
        file_put_contents($path, $bytes);

        return new UploadedFile($path, 'blob', 'application/octet-stream', null, true);
    }

    public function test_announcement_like_is_a_single_toggle_endpoint(): void
    {
        $announcement = Announcement::create(['title' => 'A', 'body' => 'B']);

        $this->postJson("/api/announcements/{$announcement->id}/like", [], $this->headers())
            ->assertOk()->assertJson(['liked' => true, 'likes_count' => 1]);

        $this->postJson("/api/announcements/{$announcement->id}/like", [], $this->headers())
            ->assertOk()->assertJson(['liked' => false, 'likes_count' => 0]);
    }

    public function test_comment_like_is_a_single_toggle_endpoint(): void
    {
        $announcement = Announcement::create(['title' => 'A', 'body' => 'B']);
        $comment = AnnouncementComment::create([
            'announcement_id' => $announcement->id,
            'user_id' => $this->authUser->id,
            'body' => 'hi',
        ]);

        $this->postJson("/api/announcements/comments/{$comment->id}/like", [], $this->headers())
            ->assertOk()->assertJson(['liked' => true, 'likes_count' => 1]);

        $this->postJson("/api/announcements/comments/{$comment->id}/like", [], $this->headers())
            ->assertOk()->assertJson(['liked' => false, 'likes_count' => 0]);
    }

    public function test_uploaded_image_stores_a_relative_path_not_a_url(): void
    {
        $file = UploadedFile::fake()->image('photo.png');

        $response = $this->postJson('/api/announcements/media/image', ['media' => $file], $this->headers())
            ->assertCreated();

        $mediaPath = $response->json('media_path');
        $this->assertStringStartsWith('announcements/media/', $mediaPath);
        $this->assertStringNotContainsString('http', $mediaPath);

        $announcement = Announcement::create([
            'title' => 'A', 'body' => 'B',
            'media_path' => $mediaPath, 'media_type' => 'image',
        ]);

        $this->assertSame(
            rtrim((string) config('filesystems.disks.public.url'), '/').'/'.$mediaPath,
            $announcement->media_url
        );
    }

    public function test_moving_the_host_does_not_break_an_already_stored_path(): void
    {
        $announcement = Announcement::create([
            'title' => 'A', 'body' => 'B',
            'media_path' => 'announcements/media/existing.png', 'media_type' => 'image',
        ]);

        config(['filesystems.disks.public.url' => 'http://new-host.test/storage']);

        $this->assertSame(
            'http://new-host.test/storage/announcements/media/existing.png',
            $announcement->fresh()->media_url
        );
    }

    public function test_an_external_gif_url_passes_through_the_accessor_unchanged(): void
    {
        $announcement = Announcement::create([
            'title' => 'A', 'body' => 'B',
            'media_path' => 'https://media.giphy.com/media/abc/giphy.gif', 'media_type' => 'gif',
        ]);

        $this->assertSame('https://media.giphy.com/media/abc/giphy.gif', $announcement->media_url);
    }

    public function test_video_upload_uuid_is_issued_by_the_backend_not_the_frontend(): void
    {
        $init = $this->postJson('/api/announcements/media/video/uploads', [
            'total_chunks' => 1,
            'total_size' => 10,
            'original_filename' => 'promo.mp4',
        ], $this->headers())->assertCreated();

        $uploadUuid = $init->json('upload_uuid');
        $this->assertNotEmpty($uploadUuid);

        // A uuid the frontend made up itself was never issued by init() —
        // there is no meta.json for it, so it's rejected.
        $fakeUuid = Str::uuid()->toString();
        $this->postJson("/api/announcements/media/video/uploads/{$fakeUuid}/chunks/0", [
            'chunk' => $this->realSizedChunk('abc'),
        ], $this->headers())->assertUnprocessable();

        $result = $this->postJson("/api/announcements/media/video/uploads/{$uploadUuid}/chunks/0", [
            'chunk' => $this->realSizedChunk('abcdefghij'),
            'width' => 1280,
            'height' => 720,
            'duration_seconds' => 42,
        ], $this->headers())->assertCreated();

        $result->assertJson([
            'status' => 'complete',
            'media_type' => 'video',
            'width' => 1280,
            'height' => 720,
            'duration_seconds' => 42,
        ]);
        $this->assertStringStartsWith('announcements/media/', $result->json('media_path'));
    }

    public function test_video_upload_is_rejected_upfront_by_declared_size(): void
    {
        config(['services.announcements.max_video_bytes' => 100]);

        $this->postJson('/api/announcements/media/video/uploads', [
            'total_chunks' => 1,
            'total_size' => 1000,
            'original_filename' => 'huge.mp4',
        ], $this->headers())->assertUnprocessable();
    }
}
