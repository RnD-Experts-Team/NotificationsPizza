<?php

namespace App\Http\Controllers;

use App\Http\Requests\Announcement\StoreAnnouncementCommentRequest;
use App\Models\Announcement;
use App\Models\AnnouncementComment;
use App\Services\Announcement\AnnouncementCommentService;
use App\Services\Announcement\LikeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementCommentController extends Controller
{
    public function __construct(
        private readonly AnnouncementCommentService $service,
        private readonly LikeService $likeService,
    ) {
    }

    public function index(Request $request, Announcement $announcement): JsonResponse
    {
        $perPage = max(1, (int) $request->integer('per_page', 15));
        $userId = (int) $request->user()->id;

        return response()->json(
            $this->service->listForAnnouncement($announcement->id, $userId, $perPage)
        );
    }

    public function store(StoreAnnouncementCommentRequest $request, Announcement $announcement): JsonResponse
    {
        $userId = (int) $request->user()->id;

        $comment = $this->service->create($announcement->id, $userId, $request->validated());

        return response()->json($comment, 201);
    }

    public function destroy(Request $request, AnnouncementComment $comment): JsonResponse
    {
        $userId = (int) $request->user()->id;

        $this->service->delete($comment, $userId);

        return response()->json([
            'message' => 'Comment deleted successfully.',
        ]);
    }

    public function like(Request $request, AnnouncementComment $comment): JsonResponse
    {
        $userId = (int) $request->user()->id;

        return response()->json($this->likeService->like($comment, $userId));
    }

    public function unlike(Request $request, AnnouncementComment $comment): JsonResponse
    {
        $userId = (int) $request->user()->id;

        return response()->json($this->likeService->unlike($comment, $userId));
    }
}
