<?php

namespace App\Services\Announcement;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Chunked video upload, ported from the proven implementation in the
 * sibling `new-courses` project (app/Services/Video/VideoChunkUploadService.php).
 * Unlike that reference, the finalized file lands on a publicly servable
 * disk (announcements have no access restriction), and an abandoned upload
 * is also cleaned up by a scheduled command, not just the manual revert
 * endpoint — see CleanupAbandonedAnnouncementChunks.
 */
class AnnouncementVideoChunkUploadService
{
    private const CHUNKS_DISK = 'announcements_chunks';
    private const MEDIA_DISK = 'announcements';
    private const ALLOWED_EXTENSIONS = ['mp4', 'mov', 'webm'];

    public function __construct(
        private readonly AnnouncementMediaService $mediaService,
    ) {
    }

    private function maxVideoBytes(): int
    {
        return (int) config('announcements.max_video_bytes');
    }

    public function uploadChunk(
        UploadedFile $chunk,
        string $uuid,
        int $index,
        int $total,
        ?string $filename,
        ?int $totalSize = null,
    ): array {
        $dir = 'chunks/'.$uuid;
        $disk = Storage::disk(self::CHUNKS_DISK);

        // Rejected before storing anything at all, on the strength of the
        // size the client declared up front — this is what actually stops
        // a 1GB upload from "starting" (no chunk ever written), rather than
        // only catching it once enough chunks have already landed on disk
        // to cross the limit (the check below, kept as a safety net in case
        // a client lies about total_size or omits it).
        if ($index === 0 && $totalSize !== null && $totalSize > $this->maxVideoBytes()) {
            throw ValidationException::withMessages([
                'total_size' => 'Video exceeds the '.$this->maxVideoBytes().'-byte limit ('.$totalSize.' bytes declared). Upload rejected before any chunk was stored.',
            ]);
        }

        $disk->put("{$dir}/chunk_{$index}", file_get_contents($chunk->getRealPath()));

        $chunkFiles = $disk->files($dir);
        $receivedBytes = array_sum(array_map(fn (string $f) => $disk->size($f), $chunkFiles));

        // Checked as chunks arrive, not only after the final merge: an
        // oversized upload is rejected — and its temp chunks deleted —
        // as soon as the running total crosses the limit, instead of
        // accepting every chunk of a file that was always going to be
        // rejected. Bounds how much disk a doomed upload can waste to
        // roughly one chunk over the limit, not the whole file.
        if ($receivedBytes > $this->maxVideoBytes()) {
            $disk->deleteDirectory($dir);

            throw ValidationException::withMessages([
                'chunk' => 'Video exceeds the '.$this->maxVideoBytes().'-byte limit ('.$receivedBytes.' bytes received so far). Upload stopped.',
            ]);
        }

        $received = count($chunkFiles);

        if ($received >= $total) {
            $originalFilename = $filename ?? ($index === 0 ? 'video.mp4' : $this->resolveFilename($uuid));

            return $this->finalize($uuid, $originalFilename, $total);
        }

        return [
            'status' => 'pending',
            'received' => $received,
            'total' => $total,
        ];
    }

    public function finalize(string $uuid, string $originalFilename, int $total): array
    {
        $dir = 'chunks/'.$uuid;
        $files = Storage::disk(self::CHUNKS_DISK)->files($dir);

        if (count($files) < $total) {
            throw ValidationException::withMessages([
                'chunk' => "Upload incomplete: expected {$total} chunks, received ".count($files).'.',
            ]);
        }

        $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION) ?: 'mp4');

        if (!in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            $this->revert($uuid);

            throw ValidationException::withMessages([
                'original_filename' => 'Unsupported video format: '.$extension,
            ]);
        }

        $destinationName = $uuid.'.'.$extension;
        $destinationRelative = 'media/'.$destinationName;
        $destPath = Storage::disk(self::MEDIA_DISK)->path($destinationRelative);
        $destDir = dirname($destPath);

        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }

        $out = fopen($destPath, 'wb');

        for ($i = 0; $i < $total; $i++) {
            $chunkPath = Storage::disk(self::CHUNKS_DISK)->path("{$dir}/chunk_{$i}");

            if (!file_exists($chunkPath)) {
                fclose($out);
                Storage::disk(self::MEDIA_DISK)->delete($destinationRelative);

                throw ValidationException::withMessages([
                    'chunk' => "Chunk {$i} is missing. Cannot finalize upload.",
                ]);
            }

            $in = fopen($chunkPath, 'rb');
            stream_copy_to_stream($in, $out);
            fclose($in);
        }

        fclose($out);

        $fileSize = filesize($destPath);

        Storage::disk(self::CHUNKS_DISK)->deleteDirectory($dir);

        // Defense in depth only — uploadChunk() already rejects before we
        // ever get here, this just guards against that check somehow being
        // bypassed (e.g. a direct finalize() call).
        if ($fileSize > $this->maxVideoBytes()) {
            Storage::disk(self::MEDIA_DISK)->delete($destinationRelative);

            throw ValidationException::withMessages([
                'chunk' => 'Video exceeds the '.$this->maxVideoBytes().'-byte limit once reassembled ('.$fileSize.' bytes).',
            ]);
        }

        return [
            'status' => 'complete',
            'media_url' => $this->mediaService->toUrl($destinationRelative),
            'media_type' => 'video',
            'file_size' => $fileSize,
        ];
    }

    public function revert(string $uuid): void
    {
        if (!Str::isUuid($uuid)) {
            return;
        }

        Storage::disk(self::CHUNKS_DISK)->deleteDirectory('chunks/'.$uuid);
    }

    private function resolveFilename(string $uuid): string
    {
        Log::warning("AnnouncementVideoChunkUploadService: original_filename missing for uuid={$uuid}, using fallback.");

        return 'video.mp4';
    }
}
