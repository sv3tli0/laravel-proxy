<?php

declare(strict_types=1);

namespace Lararoxy\Http;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Lararoxy\Data\GroupDefinition;
use Lararoxy\Data\RouteDefinition;
use Lararoxy\Outgoing\CallbackRouter;
use Lararoxy\Support\ConfigRegistry;

class RouteRegistrar
{
    public function __construct(
        protected ConfigRegistry $registry,
    ) {}

    /**
     * Register all proxy routes from the ConfigRegistry into Laravel's router.
     */
    public function register(): void
    {
        foreach ($this->registry->allGroups() as $group) {
            $this->registerGroup($group);
        }
    }

    protected function registerGroup(GroupDefinition $group): void
    {
        $attributes = array_filter([
            'prefix' => $group->prefix ?: null,
            'domain' => $group->domain,
            'middleware' => ! empty($group->middleware) ? $group->middleware : null,
        ]);

        Route::group($attributes, function () use ($group) {
            foreach ($group->routes as $route) {
                $this->registerRoute($group, $route);
            }
        });
    }

    protected function registerRoute(GroupDefinition $group, RouteDefinition $route): void
    {
        $method = strtolower($route->method);
        $path = ltrim($route->path, '/');

        // Wildcard routes use catch-all
        if ($route->wildcard) {
            $path = rtrim($path, '/').'/{path?}';
        }

        Route::$method($path, function (Request $request) use ($group, $route) {
            $controller = app(ProxyController::class);

            return $controller->handle($request, $group, $route);
        })->name("lararoxy.{$group->name}.{$route->path}");
    }

    /**
     * Register webhook callback routes from outgoing service configs.
     */
    public function registerWebhooks(): void
    {
        foreach ($this->registry->allOutgoing() as $outgoing) {
            if ($outgoing->callback === null) {
                continue;
            }

            $path = ltrim($outgoing->callback->path, '/');

            Route::post($path, function (Request $request, string $tracking_id = '') use ($outgoing) {
                $router = app(CallbackRouter::class);

                return $router->handle(
                    $request,
                    $tracking_id,
                    $outgoing->name,
                    $outgoing->callback,
                );
            })->name("lararoxy.webhook.{$outgoing->name}");
        }
    }
}
