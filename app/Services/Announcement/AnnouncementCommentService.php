<?php

namespace App\Services\Announcement;

use App\Models\AnnouncementComment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AnnouncementCommentService
{
    public function __construct(
        private readonly AnnouncementMediaService $mediaService,
    ) {
    }

    /**
     * Oldest-first (chat-style) — see the open question in the task list
     * about whether the manager wants it the other way around.
     */
    public function listForAnnouncement(int $announcementId, int $userId, int $perPage = 15): LengthAwarePaginator
    {
        return AnnouncementComment::query()
            ->where('announcement_id', $announcementId)
            ->with('user:id,name')
            ->withCount('likes')
            ->with(['likes' => function ($query) use ($userId) {
                $query->where('user_id', $userId);
            }])
            ->orderBy('created_at')
            ->paginate($perPage)
            ->through(function (AnnouncementComment $comment) {
                $comment->setAttribute('liked_by_me', $comment->likes->isNotEmpty());
                $comment->makeHidden('likes');

                return $comment;
            });
    }

    public function create(int $announcementId, int $userId, array $data): AnnouncementComment
    {
        return AnnouncementComment::query()->create([
            'announcement_id' => $announcementId,
            'user_id' => $userId,
            'body' => $data['body'] ?? null,
            'media_path' => $data['media_path'] ?? null,
            'media_type' => $data['media_type'] ?? null,
        ])->load('user:id,name');
    }

    /**
     * Only the comment's own author may delete it. There is no admin-override
     * here yet — the open question about whether an admin/role flag should
     * also be allowed to delete comments hasn't been answered.
     */
    public function delete(AnnouncementComment $comment, int $requestingUserId): void
    {
        if ($comment->user_id !== $requestingUserId) {
            throw new AuthorizationException('You can only delete your own comment.');
        }

        $mediaPath = $comment->media_path;

        $comment->delete();

        $this->mediaService->deleteByPath($mediaPath);
    }
}
