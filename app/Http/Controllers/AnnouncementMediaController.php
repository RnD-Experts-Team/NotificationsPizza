<?php

namespace App\Http\Controllers;

use App\Http\Requests\Announcement\AnnouncementGifSearchRequest;
use App\Http\Requests\Announcement\AnnouncementVideoChunkUploadRequest;
use App\Http\Requests\Announcement\InitAnnouncementVideoUploadRequest;
use App\Http\Requests\Announcement\UploadAnnouncementImageRequest;
use App\Services\Announcement\AnnouncementMediaService;
use App\Services\Announcement\AnnouncementVideoChunkUploadService;
use App\Services\Announcement\GifProviderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementMediaController extends Controller
{
    public function __construct(
        private readonly AnnouncementMediaService $mediaService,
        private readonly GifProviderService $gifProviderService,
        private readonly AnnouncementVideoChunkUploadService $videoChunkUploadService,
    ) {
    }

    public function uploadImage(UploadAnnouncementImageRequest $request): JsonResponse
    {
        return response()->json(
            $this->mediaService->uploadImage($request->file('media')),
            201
        );
    }

    public function gifsTrending(Request $request): JsonResponse
    {
        $limit = max(1, min(50, (int) $request->integer('limit', 24)));
        $offset = max(0, (int) $request->integer('offset', 0));

        return response()->json([
            'data' => $this->gifProviderService->trending($limit, $offset),
        ]);
    }

    public function gifsSearch(AnnouncementGifSearchRequest $request): JsonResponse
    {
        $data = $request->validated();

        return response()->json([
            'data' => $this->gifProviderService->search(
                $data['q'],
                $data['limit'] ?? 24,
                $data['offset'] ?? 0
            ),
        ]);
    }

    public function initVideoUpload(InitAnnouncementVideoUploadRequest $request): JsonResponse
    {
        $data = $request->validated();

        return response()->json(
            $this->videoChunkUploadService->initUpload(
                $data['total_chunks'],
                $data['total_size'],
                $data['original_filename'],
            ),
            201
        );
    }

    public function uploadVideoChunk(AnnouncementVideoChunkUploadRequest $request, string $uploadUuid, int $chunkIndex): JsonResponse
    {
        $data = $request->validated();

        $result = $this->videoChunkUploadService->uploadChunk(
            $request->file('chunk'),
            $uploadUuid,
            $chunkIndex,
            $data['width'] ?? null,
            $data['height'] ?? null,
            $data['duration_seconds'] ?? null,
        );

        return response()->json($result, $result['status'] === 'complete' ? 201 : 202);
    }

    public function revertVideoChunk(string $uploadUuid): JsonResponse
    {
        $this->videoChunkUploadService->revert($uploadUuid);

        return response()->json(['message' => 'Upload reverted.']);
    }
}
