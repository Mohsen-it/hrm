<?php

namespace Modules\UserActivity\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Modules\UserActivity\Services\UserActivityService;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * RecordUserActivity — append-only audit of every authenticated request.
 *
 * The row is written after the response is produced so a slow or failing
 * logging step can never delay or break the request itself.
 */
class RecordUserActivity
{
    /**
     * Route names (or prefixes) that must never be recorded.
     *
     * @var array<int, string>
     */
    private const SKIPPED = [
        'login',
        'logout',
        'user-activity',
        'dashboard.pullEvents',
        'dashboard.snapshot',
        'horizon',
        'telescope',
        'ignition',
        '_debugbar',
    ];

    public function __construct(private UserActivityService $service) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            $user = $request->user();

            if ($user === null || $request->isMethod('HEAD')) {
                return $response;
            }

            $route = $request->route();
            $routeName = $route?->getName();

            if ($routeName === null || $this->isSkipped($routeName)) {
                return $response;
            }

            [$action, $entity] = $this->resolveAction($routeName, $request);

            $this->service->record(
                userId: (int) $user->getAuthIdentifier(),
                action: $action,
                entity: $entity,
                method: $request->method(),
                url: $this->normalizeUrl($request),
                ip: $request->ip(),
                userAgent: $request->userAgent()
            );
        } catch (Throwable) {
            // Activity logging must never take a request down.
        }

        return $response;
    }

    /**
     * Whether the route name is in the skip list (exact or prefix match).
     */
    private function isSkipped(string $routeName): bool
    {
        foreach (self::SKIPPED as $prefix) {
            if ($routeName === $prefix || str_starts_with($routeName, $prefix.'.')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Derive the logical action and entity from the route name.
     *
     * e.g. `vacations.requests.store`   → create     / vacations.requests
     *      `vacations.requests.create`  → open_create / vacations.requests
     *      `vacations.requests.destroy` → delete     / vacations.requests
     *      `users.index`                → view       / users
     *      `dashboard`                  → view       / dashboard
     *
     * Form pages (GET create / edit) are recorded as `open_create` /
     * `open_edit` so a report can tell "opened the add page" apart from an
     * actual store request — someone may open a form and never submit it.
     *
     * @return array{0: string, 1: string}
     */
    private function resolveAction(string $routeName, Request $request): array
    {
        $verbs = [
            'store' => 'create',
            'create' => 'open_create',
            'update' => 'edit',
            'edit' => 'open_edit',
            'destroy' => 'delete',
            'index' => 'view',
            'show' => 'view',
            'publish' => 'publish',
            'regenerate' => 'regenerate',
            'assign' => 'assign',
            'unassign' => 'unassign',
            'transfer' => 'transfer',
            'export' => 'export',
            'approve' => 'approve',
            'reject' => 'reject',
            'cancel' => 'cancel',
            'sync' => 'sync',
            'adjust' => 'adjust',
            'set' => 'set',
            'grant-all' => 'grant',
            'switch' => 'switch',
            'attach' => 'assign',
            'detach' => 'unassign',
            'copy' => 'copy',
            'bulk-delete' => 'delete',
            'bulk-assign' => 'assign',
            'bulk-unassign' => 'unassign',
            'bulk-transfer' => 'transfer',
            'bulk-update' => 'edit',
        ];

        $segments = explode('.', $routeName);
        $last = (string) end($segments);

        if (isset($verbs[$last])) {
            $action = $verbs[$last];
            $entitySegments = array_slice($segments, 0, -1);
        } else {
            $action = match ($request->method()) {
                'POST' => 'create',
                'PUT', 'PATCH' => 'edit',
                'DELETE' => 'delete',
                default => 'view',
            };
            $entitySegments = $segments;
        }

        $entity = implode('.', $entitySegments);

        return [$action, $entity === '' ? 'other' : $entity];
    }

    private function normalizeUrl(Request $request): string
    {
        $query = $request->getQueryString();

        return $request->path().($query ? '?'.$query : '');
    }
}
