<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * GET /api/announcements/calendar/feed?stores=03795-00001[,…]&from=YYYY-MM-DD&to=YYYY-MM-DD
 *
 * Notifications' part of the unified calendar (calendar-feed/v1): one
 * `announcement` event per active announcement that overlaps the range —
 * future ones included, unlike /visible which only knows "now".
 *
 * Announcements belong to the whole company, so every event has store = null
 * and the answer is the same whatever stores are asked for. `stores` is still
 * required so the request has the same shape as every other feed and pizzasys
 * checks it. This service has no stores table, so it cannot refuse an unknown
 * number the way the other feeds do — and does not need to: nothing in the
 * response depends on the store.
 *
 * The announcement `body` is never returned: the calendar shows the title and
 * links to the announcement.
 */
class CalendarFeedController extends Controller
{
    private const MAX_STORES = 50;

    private const MAX_DAYS = 62;

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'stores' => ['required', 'string'],
            'from'   => ['required', 'date_format:Y-m-d'],
            'to'     => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $numbers = array_values(array_unique(array_filter(array_map('trim', explode(',', $data['stores'])))));
        if ($numbers === [] || count($numbers) > self::MAX_STORES) {
            throw ValidationException::withMessages(['stores' => 'Send 1 to ' . self::MAX_STORES . ' store numbers.']);
        }

        $from = Carbon::parse($data['from'], 'UTC')->startOfDay();
        $to = Carbon::parse($data['to'], 'UTC')->endOfDay();
        if ($from->diffInDays($to->copy()->startOfDay()) > self::MAX_DAYS) {
            throw ValidationException::withMessages(['to' => 'Date range may not exceed ' . self::MAX_DAYS . ' days.']);
        }

        // Overlaps [from, to]: starts before the range ends (or has no start —
        // then it counts from when it was created), and ends after it begins
        // (or never ends).
        $events = Announcement::query()
            ->where('is_active', true)
            ->whereRaw('COALESCE(starts_at, created_at) <= ?', [$to])
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $from))
            ->orderByRaw('COALESCE(starts_at, created_at)')
            ->orderBy('id')
            ->get(['id', 'type', 'title', 'is_pinned', 'starts_at', 'ends_at', 'created_at', 'updated_at'])
            ->map(fn (Announcement $a) => $this->event($a))
            ->all();

        return response()->json([
            'contract'     => 'calendar-feed/v1',
            'source'       => 'notifications',
            'generated_at' => $this->utc(now()),
            'range'        => ['from' => $data['from'], 'to' => $data['to']],
            'stores'       => array_map(fn ($n) => ['number' => $n, 'timezone' => 'UTC'], $numbers),
            'events'       => $events,
            'meta'         => ['count' => count($events), 'truncated' => false],
        ]);
    }

    /** @return array<string, mixed> */
    private function event(Announcement $a): array
    {
        $start = ($a->starts_at ?? $a->created_at)->copy()->utc();

        return [
            'id'         => 'notifications:announcement:' . $a->id,
            'type'       => 'announcement',
            'layer'      => 'company',
            'store'      => null,
            'title'      => mb_substr($a->title, 0, 120),
            'all_day'    => true,
            'date'       => $start->toDateString(),
            'date_end'   => $a->ends_at?->copy()->utc()->toDateString(),
            'start'      => null,
            'end'        => null,
            'status'     => 'info',
            'people'     => [],
            // Same path the announcement notifications already send, so the
            // dashboard needs no new route.
            'link'       => '/announcements/' . $a->id,
            'updated_at' => $this->utc($a->updated_at),
            'extra'      => [
                'is_pinned'  => (bool) $a->is_pinned,
                'kind'       => $a->type,
                'open_ended' => $a->ends_at === null,
            ],
        ];
    }

    private function utc($at): ?string
    {
        return $at ? Carbon::parse($at)->utc()->format('Y-m-d\TH:i:s\Z') : null;
    }
}
