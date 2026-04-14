<?php

declare(strict_types=1);

namespace Lararoxy\Builders;

class RouteCollector
{
    /** @var array<RouteBuilder|AggregateBuilder> */
    protected array $routes = [];

    public function get(string $path, string $upstream, string $upstreamPath): RouteBuilder
    {
        return $this->addRoute('GET', $path, $upstream, $upstreamPath);
    }

    public function post(string $path, string $upstream, string $upstreamPath): RouteBuilder
    {
        return $this->addRoute('POST', $path, $upstream, $upstreamPath);
    }

    public function put(string $path, string $upstream, string $upstreamPath): RouteBuilder
    {
        return $this->addRoute('PUT', $path, $upstream, $upstreamPath);
    }

    public function patch(string $path, string $upstream, string $upstreamPath): RouteBuilder
    {
        return $this->addRoute('PATCH', $path, $upstream, $upstreamPath);
    }

    public function delete(string $path, string $upstream, string $upstreamPath): RouteBuilder
    {
        return $this->addRoute('DELETE', $path, $upstream, $upstreamPath);
    }

    public function aggregate(string $path): AggregateBuilder
    {
        $builder = new AggregateBuilder($path);
        $this->routes[] = $builder;

        return $builder;
    }

    protected function addRoute(string $method, string $path, string $upstream, string $upstreamPath): RouteBuilder
    {
        $route = new RouteBuilder($method, $path, $upstream, $upstreamPath);
        $this->routes[] = $route;

        return $route;
    }

    /** @return array<RouteBuilder> */
    public function getRoutes(): array
    {
        return $this->routes;
    }
}
