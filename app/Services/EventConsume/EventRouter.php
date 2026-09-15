<?php

namespace App\Services\EventConsume;

use App\Services\EventConsume\Handlers\BroadcastSendHandler;
use App\Services\EventConsume\Handlers\EmailSendHandler;
use App\Services\EventConsume\Handlers\NotificationSendHandler;
use App\Services\EventConsume\Handlers\RoleEmailSendHandler;
use App\Services\EventConsume\Handlers\RoleNotificationSendHandler;
use App\Services\EventConsume\Handlers\UserCreatedHandler;
use App\Services\EventConsume\Handlers\UserDeletedHandler;
use App\Services\EventConsume\Handlers\UserDeviceUpsertedHandler;
use App\Services\EventConsume\Handlers\UserUpdatedHandler;
use App\Services\EventConsume\Handlers\UserStoreRoleAssignedHandler;
use App\Services\EventConsume\Handlers\UserStoreRoleRemovedHandler;
use App\Services\EventConsume\Handlers\UserStoreRoleToggledHandler;
use App\Services\EventConsume\Handlers\UserStoreRoleBulkAssignedHandler;

use Exception;

class EventRouter
{
    /** @var array<string, class-string<EventHandlerInterface>> */
    private array $map;

    public function __construct()
    {
        $devMode = (bool) config('nats.dev_mode');

        $authPrefix = $devMode
            ? 'auth.testing.v1'
            : 'auth.v1';

        $notificationsPrefix = $devMode
            ? 'notifications.testing.v1'
            : 'notifications.v1';

        $this->map = [

            // USERS
            "{$authPrefix}.user.created" => UserCreatedHandler::class,
            "{$authPrefix}.user.updated" => UserUpdatedHandler::class,
            "{$authPrefix}.user.deleted" => UserDeletedHandler::class,

            // ASSIGNMENTS => replicate qa_auditor into user_store_roles
            "{$authPrefix}.assignment.user_role_store.assigned" => UserStoreRoleAssignedHandler::class,
            "{$authPrefix}.assignment.user_role_store.removed" => UserStoreRoleRemovedHandler::class,
            "{$authPrefix}.assignment.user_role_store.toggled" => UserStoreRoleToggledHandler::class,
            "{$authPrefix}.assignment.user_role_store.bulk_assigned" => UserStoreRoleBulkAssignedHandler::class,

            // DEVICES
            "{$authPrefix}.user.device.upserted" => UserDeviceUpsertedHandler::class,

            // COMMUNICATION
            "{$notificationsPrefix}.email.send" => EmailSendHandler::class,
            "{$notificationsPrefix}.notification.send" => NotificationSendHandler::class,

            // LIVE STATE — broadcast only, persists nothing. Same Reverb and
            // same private-users.{id} channel as a notification, but no
            // InAppNotification row, so a producer can push a moving figure
            // without filling the bell. The producer names its own event.
            "{$notificationsPrefix}.broadcast.send" => BroadcastSendHandler::class,

            // COMMUNICATION — role/store targeted (resolves recipients from user_store_roles)
            "{$notificationsPrefix}.notification.role.send" => RoleNotificationSendHandler::class,
            "{$notificationsPrefix}.email.role.send" => RoleEmailSendHandler::class,
        ];
    }
    public function getResolvedMap(): array
    {
        return $this->map;
    }
    public function resolve(string $subject): string
    {
        if (!isset($this->map[$subject])) {
            throw new Exception("No handler for subject '{$subject}'");
        }

        return $this->map[$subject];
    }
}