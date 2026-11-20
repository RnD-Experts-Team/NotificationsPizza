<?php

namespace App\Http\Controllers;

use App\Http\Requests\Announcement\MarkAnnouncementsSeenRequest;
use App\Http\Requests\Announcement\StoreAnnouncementRequest;
use App\Http\Requests\Announcement\UpdateAnnouncementRequest;
use App\Models\Announcement;
use App\Services\AnnouncementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnouncementController extends Controller
{
    public function __construct(
        private readonly AnnouncementService $service,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, (int) $request->integer('per_page', 15));
        $userId = (int) $request->user()->id;

        return response()->json(
            $this->service->paginateForAdmin($perPage, $userId)
        );
    }

    public function store(StoreAnnouncementRequest $request): JsonResponse
    {
        $announcement = $this->service->create($request->validated());

        return response()->json($announcement, 201);
    }

    public function show(Announcement $announcement): JsonResponse
    {
        return response()->json($announcement);
    }

    public function update(UpdateAnnouncementRequest $request, Announcement $announcement): JsonResponse
    {
        $updated = $this->service->update($announcement, $request->validated());

        return response()->json($updated);
    }

    public function destroy(Announcement $announcement): JsonResponse
    {
        $this->service->delete($announcement);

        return response()->json([
            'message' => 'Announcement deleted successfully.',
        ]);
    }

    public function visible(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;

        return response()->json(
            $this->service->getVisibleForUser($userId)
        );
    }

    public function unseen(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;

        return response()->json(
            $this->service->getUnseenForUser($userId)
        );
    }

    public function markSeen(MarkAnnouncementsSeenRequest $request): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $announcementIds = $request->validated()['announcement_ids'];

        $this->service->markManySeen($userId, $announcementIds);

        return response()->json([
            'message' => 'Announcements marked as seen.',
        ]);
    }

    public function toggleLike(Request $request, Announcement $announcement): JsonResponse
    {
        $userId = (int) $request->user()->id;

        return response()->json($announcement->toggleLike($userId));
    }
}