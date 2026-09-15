<?php

namespace Tests\Feature\EventConsume;

use App\Events\TransientBroadcast;
use App\Models\InAppNotification;
use App\Services\EventConsume\EventRouter;
use App\Services\EventConsume\Handlers\BroadcastSendHandler;
use App\Services\EventConsume\Handlers\NotificationSendHandler;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * notifications.v1.broadcast.send - the transient half of the socket.
 *
 * This path exists so a producer can push live state (a running timer, a moving
 * total) to a user without adding a row to their notification bell.
 */
class BroadcastSendHandlerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $data
     */
    private function handle(array $data): void
    {
        app(BroadcastSendHandler::class)->handle(['data' => $data]);
    }

    public function test_it_broadcasts_on_the_users_private_channel(): void
    {
        Event::fake([TransientBroadcast::class]);

        $this->handle([
            'event' => 'break.updated',
            'users' => [['id' => 9, 'data' => ['counted_minutes' => 21]]],
        ]);

        Event::assertDispatched(TransientBroadcast::class, function (TransientBroadcast $e) {
            $channels = $e->broadcastOn();

            return $e->userId === 9
                && $e->broadcastAs() === 'break.updated'
                && $channels[0] instanceof PrivateChannel
                // The channel the dashboard already holds - no second socket.
                && (string) $channels[0] === 'private-users.9';
        });
    }

    /**
     * The whole point of this path: nothing reaches the bell.
     */
    public function test_it_persists_nothing(): void
    {
        $this->handle([
            'event' => 'break.started',
            'users' => [['id' => 9, 'data' => ['id' => 501]]],
        ]);

        $this->assertSame(0, InAppNotification::query()->count());
    }

    public function test_the_payload_is_passed_through_untouched(): void
    {
        Event::fake([TransientBroadcast::class]);

        $payload = ['entry' => ['id' => 501, 'label' => 'Coffee break'], 'totals' => ['counted_minutes' => 21]];

        $this->handle(['event' => 'break.updated', 'users' => [['id' => 9, 'data' => $payload]]]);

        Event::assertDispatched(TransientBroadcast::class, function (TransientBroadcast $e) use ($payload) {
            $wire = $e->broadcastWith();

            return $wire['data'] === $payload
                && $wire['event'] === 'break.updated'
                && $wire['user_id'] === 9
                && array_key_exists('broadcast_at', $wire);
        });
    }

    public function test_it_broadcasts_once_per_recipient(): void
    {
        Event::fake([TransientBroadcast::class]);

        $this->handle([
            'event' => 'ticket.assigned',
            'users' => [['id' => 9, 'data' => []], ['id' => 77, 'data' => []]],
        ]);

        Event::assertDispatchedTimes(TransientBroadcast::class, 2);
    }

    public function test_a_missing_event_name_is_rejected(): void
    {
        $this->expectExceptionMessage('data.event must be a non-empty string');

        $this->handle(['users' => [['id' => 9, 'data' => []]]]);
    }

    public function test_an_empty_recipient_list_is_rejected(): void
    {
        $this->expectExceptionMessage('data.users must be a non-empty array');

        $this->handle(['event' => 'break.updated', 'users' => []]);
    }

    public function test_a_recipient_without_an_id_is_rejected(): void
    {
        $this->expectExceptionMessage('users[0].id is required');

        $this->handle(['event' => 'break.updated', 'users' => [['data' => []]]]);
    }

    public function test_the_router_resolves_the_new_subject(): void
    {
        $this->assertSame(
            BroadcastSendHandler::class,
            app(EventRouter::class)->resolve('notifications.v1.broadcast.send'),
        );
    }

    /**
     * This addition is purely additive - the existing subjects must still map
     * exactly where they did.
     */
    public function test_the_existing_notification_subject_is_untouched(): void
    {
        $map = app(EventRouter::class)->getResolvedMap();

        $this->assertSame(NotificationSendHandler::class, $map['notifications.v1.notification.send']);
        $this->assertArrayHasKey('notifications.v1.notification.role.send', $map);
        $this->assertArrayHasKey('notifications.v1.email.send', $map);
        $this->assertArrayHasKey('auth.v1.user.created', $map);
    }

    public function test_dev_mode_exposes_the_testing_twin_of_the_subject(): void
    {
        config(['nats.dev_mode' => true]);

        $this->assertArrayHasKey(
            'notifications.testing.v1.broadcast.send',
            (new EventRouter)->getResolvedMap(),
        );
    }
}
