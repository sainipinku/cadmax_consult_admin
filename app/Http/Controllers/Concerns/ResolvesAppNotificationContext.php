<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Notification;
use App\Models\SuperAdmin;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

trait ResolvesAppNotificationContext
{
    /**
     * Resolve the active authenticated notification model key and actor instance.
     *
     * @param  array<int, string>  $guardPriority
     * @return array{model: string, user: object}
     */
    protected function resolveNotificationContext(array $guardPriority = ['admin', 'member', 'callingteam', 'superadmin']): array
    {
        foreach ($guardPriority as $guard) {
            if (Auth::guard($guard)->check()) {
                $user = Auth::guard($guard)->user();
                if ($user) {
                    return ['model' => $guard, 'user' => $user];
                }
            }
        }

        abort(401, 'Unauthenticated.');
    }

    /**
     * Guard order preference used by this controller's notification endpoints.
     *
     * @return array<int, string>
     */
    protected function notificationGuardPriority(): array
    {
        return ['admin', 'member', 'callingteam', 'superadmin'];
    }

    protected function notificationQueryForActor(object $user, string $model): HasMany
    {
        return $user instanceof SuperAdmin
            ? $user->notifications()
            : $user->appNotifications($model);
    }

    protected function unreadNotificationsCountForActor(object $user, string $model): int
    {
        return $user instanceof SuperAdmin
            ? $user->unreadNotificationsCount()
            : $user->unreadAppNotificationsCount($model);
    }

    protected function markAllNotificationsReadForActor(object $user, string $model): int
    {
        return $user instanceof SuperAdmin
            ? $user->markAllNotificationsAsRead()
            : $user->markAllAppNotificationsAsRead($model);
    }

    protected function deleteAllNotificationsForActor(object $user, string $model): int
    {
        return $user instanceof SuperAdmin
            ? $user->deleteAllNotifications()
            : $user->deleteAllAppNotifications($model);
    }

    public function notificationsUnreadCount(Request $request): JsonResponse
    {
        $context = $this->resolveNotificationContext($this->notificationGuardPriority());

        return response()->json([
            'success' => true,
            'unread' => $this->unreadNotificationsCountForActor($context['user'], $context['model']),
        ]);
    }

    public function notificationsList(Request $request): JsonResponse
    {
        $context = $this->resolveNotificationContext($this->notificationGuardPriority());
        $model = $context['model'];
        $user = $context['user'];
        $perPage = max(1, min((int) $request->input('per_page', 15), 50));

        $query = $this->notificationQueryForActor($user, $model)
            ->with(['job:id,uuid,title,company,status,created_by'])
            ->orderByDesc('created_at');

        if ($request->filled('type')) {
            $query->where('type', $request->string('type')->toString());
        }

        $notifications = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'success' => true,
            'data' => $notifications,
            'unread' => $this->unreadNotificationsCountForActor($user, $model),
        ]);
    }

    public function notificationsMarkRead(Request $request, Notification $notification): JsonResponse
    {
        $context = $this->resolveNotificationContext($this->notificationGuardPriority());
        $model = $context['model'];
        $user = $context['user'];

        if ($notification->model !== $model || (int) $notification->listing_id !== (int) $user->id) {
            abort(404);
        }

        $notification->update([
            'status' => 'read',
            'viewed_at' => $notification->viewed_at ?? now(),
        ]);

        return response()->json([
            'success' => true,
            'unread' => $this->unreadNotificationsCountForActor($user, $model),
        ]);
    }

    public function notificationsMarkAllRead(Request $request): JsonResponse
    {
        $context = $this->resolveNotificationContext($this->notificationGuardPriority());
        $model = $context['model'];
        $user = $context['user'];

        $updated = $this->markAllNotificationsReadForActor($user, $model);

        return response()->json([
            'success' => true,
            'updated' => $updated,
            'unread' => $this->unreadNotificationsCountForActor($user, $model),
        ]);
    }

    public function notificationsDelete(Request $request, Notification $notification): JsonResponse
    {
        $context = $this->resolveNotificationContext($this->notificationGuardPriority());
        $model = $context['model'];
        $user = $context['user'];

        if ($notification->model !== $model || (int) $notification->listing_id !== (int) $user->id) {
            abort(404);
        }

        $notification->delete();

        return response()->json([
            'success' => true,
            'unread' => $this->unreadNotificationsCountForActor($user, $model),
        ]);
    }

    public function notificationsDeleteAll(Request $request): JsonResponse
    {
        $context = $this->resolveNotificationContext($this->notificationGuardPriority());
        $model = $context['model'];
        $user = $context['user'];

        $deleted = $this->deleteAllNotificationsForActor($user, $model);

        return response()->json([
            'success' => true,
            'deleted' => $deleted,
            'unread' => 0,
        ]);
    }
}
