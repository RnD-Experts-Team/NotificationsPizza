<?php

namespace App\Services\EventConsume\Handlers;

use App\Events\TransientBroadcast;
use App\Services\EventConsume\EventHandlerInterface;

/**
 * notifications.v1.broadcast.send - push live state to users' sockets without
 * persisting anything.
 *
 * Shaped like notification.send so producers read the two the same way, minus
 * `channels`: a broadcast is web by definition, and there is no mobile or email
 * equivalent of "the timer moved".
 *
 *   {
 *     "event": "break.updated",
 *     "users": [ { "id": 9, "data": { ... } } ]
 *   }
 *
 * No `->toOthers()`, unlike WebNotificationCreated. That call suppresses
 * delivery to the socket that caused the change, which is right for a
 * notification the actor triggered and wrong for state sync, where every tab
 * should converge on the same picture.
 *
 * No local user lookup either. Channel authorisation happens against the
 * SUBSCRIBER's token in routes/channels.php, so a broadcast aimed at a user who
 * has not replicated here - or who simply is not connected - is harmless and
 * lands nowhere.
 */
class BroadcastSendHandler implements EventHandlerInterface
{
    public function handle(array $event): void
    {
        $name = data_get($event, 'data.event');
        $users = data_get($event, 'data.users', []);

        if (! is_string($name) || $name === '') {
            throw new \Exception('BroadcastSendHandler: data.event must be a non-empty string');
        }

        if (! is_array($users) || empty($users)) {
            throw new \Exception('BroadcastSendHandler: data.users must be a non-empty array');
        }

        foreach ($users as $index => $user) {
            $userId = $this->asInt(data_get($user, 'id'));
            $payload = data_get($user, 'data', []);

            if ($userId <= 0) {
                throw new \Exception("BroadcastSendHandler: users[{$index}].id is required");
            }

            if (! is_array($payload)) {
                throw new \Exception("BroadcastSendHandler: users[{$index}].data must be an array");
            }

            // ShouldBroadcastNow, so this goes out on the spot rather than
            // taking a queue hop - live state that arrives late is worse than
            // useless, because the client has already drawn something else.
            broadcast(new TransientBroadcast($userId, $name, $payload));
        }
    }

    private function asInt(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return 0;
    }
}
