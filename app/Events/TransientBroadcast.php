<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A push to a user's socket that persists NOTHING.
 *
 * WebNotificationCreated is welded to an InAppNotification: SendWebNotificationJob
 * creates the row and then broadcasts it, so "reach a user's socket" and "add a
 * row to their notification bell" are currently the same act. That is right for
 * a notification and wrong for live state - a break timer or a progress figure
 * would litter the bell with rows nobody wants to read.
 *
 * This is the other half: same Reverb, same private-users.{id} channel the
 * dashboard already subscribes to, no database row.
 *
 * Deliberately domain-agnostic. The producing service names its own event
 * (`break.updated`, `ticket.assigned`, ...) and supplies an opaque payload; this
 * class knows nothing about any of them. It carries plain arrays rather than a
 * model, so there is nothing to serialize and nothing to persist.
 */
class TransientBroadcast implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public int $userId,
        public string $event,
        public array $data,
    ) {
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        // The same channel as WebNotificationCreated, so a client needs only the
        // one socket it already holds - no second Echo connection, no second
        // broadcasting/auth endpoint, no extra CSP origin.
        return [new PrivateChannel('users.'.$this->userId)];
    }

    /**
     * The producer names the event, so clients listen for `.break.updated`
     * (note the leading dot - a custom broadcast name).
     */
    public function broadcastAs(): string
    {
        return $this->event;
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'event' => $this->event,
            'user_id' => $this->userId,
            // Passed through untouched: this service does not interpret the
            // payload of a domain it knows nothing about.
            'data' => $this->data,
            // When it left here. A client reconciling several messages needs an
            // ordering it can trust more than arrival order.
            'broadcast_at' => now()->utc()->toIso8601String(),
        ];
    }
}
