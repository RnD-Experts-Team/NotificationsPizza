<?php

namespace App\Services;

use App\Models\Announcement;
use App\Models\AnnouncementUserState;
use App\Services\Announcement\AnnouncementMediaService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use App\Services\NotificationService;
use App\Jobs\SendBulkNotificationJob;

class AnnouncementService
{

    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly AnnouncementMediaService $mediaService,
    ) {
    }
    public function paginateForAdmin(int $perPage = 15, ?int $userId = null): LengthAwarePaginator
    {
        return Announcement::query()
            ->withCount('likes')
            ->when($userId !== null, fn ($query) => $query->with(['likes' => function ($q) use ($userId) {
                $q->where('user_id', $userId);
            }]))
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->through(function (Announcement $announcement) use ($userId) {
                if ($userId !== null) {
                    $announcement->setAttribute('liked_by_me', $announcement->likes->isNotEmpty());
                    $announcement->makeHidden('likes');
                }

                return $announcement;
            });
    }

    public function create(array $data): Announcement
    {
        return DB::transaction(function () use ($data) {
            $announcement = Announcement::query()->create([
                'type' => $data['type'] ?? 'general',
                'title' => $data['title'],
                'body' => $data['body'],
                'version' => $data['version'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'is_pinned' => $data['is_pinned'] ?? false,
                'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
                'media_url' => $data['media_url'] ?? null,
                'media_type' => $data['media_type'] ?? null,
                'color_start' => $data['color_start'] ?? null,
                'color_end' => $data['color_end'] ?? null,
            ]);

            dispatch(new SendBulkNotificationJob(
                channels: ['web'],
                payload: [
                    'type' => 'announcement.created',
                    'title' => $announcement->title,
                    'body' => $announcement->body,
                    'action_url' => "/announcements/{$announcement->id}",
                    'announcement_id' => $announcement->id,
                    'media_url' => $announcement->media_url,
                    'media_type' => $announcement->media_type,
                    'color_start' => $announcement->color_start,
                    'color_end' => $announcement->color_end,
                ]
            ));

            return $announcement;
        });
    }

    public function update(Announcement $announcement, array $data): Announcement
    {
        return DB::transaction(function () use ($announcement, $data) {
            $previousMediaUrl = $announcement->media_url;

            $announcement->update([
                'type' => $data['type'] ?? $announcement->type,
                'title' => $data['title'] ?? $announcement->title,
                'body' => $data['body'] ?? $announcement->body,
                'version' => array_key_exists('version', $data) ? $data['version'] : $announcement->version,
                'is_active' => $data['is_active'] ?? $announcement->is_active,
                'is_pinned' => $data['is_pinned'] ?? $announcement->is_pinned,
                'starts_at' => array_key_exists('starts_at', $data) ? $data['starts_at'] : $announcement->starts_at,
                'ends_at' => array_key_exists('ends_at', $data) ? $data['ends_at'] : $announcement->ends_at,
                'media_url' => array_key_exists('media_url', $data) ? $data['media_url'] : $announcement->media_url,
                'media_type' => array_key_exists('media_type', $data) ? $data['media_type'] : $announcement->media_type,
                'color_start' => array_key_exists('color_start', $data) ? $data['color_start'] : $announcement->color_start,
                'color_end' => array_key_exists('color_end', $data) ? $data['color_end'] : $announcement->color_end,
            ]);

            $announcement->refresh();

            if ($previousMediaUrl && $previousMediaUrl !== $announcement->media_url) {
                $this->mediaService->deleteByUrl($previousMediaUrl);
            }

            dispatch(new SendBulkNotificationJob(
                channels: ['web'],
                payload: [
                    'type' => 'announcement.updated',
                    'title' => $announcement->title,
                    'body' => $announcement->body,
                    'action_url' => "/announcements/{$announcement->id}",
                    'announcement_id' => $announcement->id,
                    'media_url' => $announcement->media_url,
                    'media_type' => $announcement->media_type,
                    'color_start' => $announcement->color_start,
                    'color_end' => $announcement->color_end,
                ]
            ));

            return $announcement;
        });
    }


    public function delete(Announcement $announcement): void
    {
        $mediaUrl = $announcement->media_url;

        $announcement->delete();

        $this->mediaService->deleteByUrl($mediaUrl);
    }

    public function getVisibleForUser(int $userId): Collection
    {
        $now = now();

        return Announcement::query()
            ->with([
                'userStates' => function ($q) use ($userId) {
                    $q->where('user_id', $userId)
                        ->whereNotNull('seen_at');
                }
            ])
            ->withCount('likes')
            ->with(['likes' => function ($q) use ($userId) {
                $q->where('user_id', $userId);
            }])
            ->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $now);
            })
            ->orderByDesc('is_pinned')
            ->orderByDesc('created_at')
            ->get()
            ->each(function (Announcement $announcement): void {
                $announcement->setAttribute('seen', $announcement->userStates->isNotEmpty());
                $announcement->setAttribute('liked_by_me', $announcement->likes->isNotEmpty());
                $announcement->makeHidden(['userStates', 'likes']);
            });
    }

    public function getUnseenForUser(int $userId): Collection
    {
        $now = now();

        return Announcement::query()
            ->with([
                'userStates' => function ($q) use ($userId) {
                    $q->where('user_id', $userId)
                        ->whereNotNull('seen_at');
                }
            ])
            ->withCount('likes')
            ->with(['likes' => function ($q) use ($userId) {
                $q->where('user_id', $userId);
            }])
            ->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $now);
            })
            ->whereDoesntHave('userStates', function ($q) use ($userId) {
                $q->where('user_id', $userId)
                    ->whereNotNull('seen_at');
            })
            ->orderByDesc('is_pinned')
            ->orderBy('created_at', 'asc') // fix here
            ->get()
            ->each(function (Announcement $announcement): void {
                $announcement->setAttribute('seen', $announcement->userStates->isNotEmpty());
                $announcement->setAttribute('liked_by_me', $announcement->likes->isNotEmpty());
                $announcement->makeHidden(['userStates', 'likes']);
            });
    }

    public function markSeen(int $userId, int $announcementId): void
    {
        AnnouncementUserState::query()->updateOrCreate(
            [
                'announcement_id' => $announcementId,
                'user_id' => $userId,
            ],
            [
                'seen_at' => now(),
            ]
        );
    }

    public function markManySeen(int $userId, array $announcementIds): void
    {
        foreach ($announcementIds as $announcementId) {
            $id = is_numeric($announcementId) ? (int) $announcementId : 0;

            if ($id > 0) {
                $this->markSeen($userId, $id);
            }
        }
    }
}