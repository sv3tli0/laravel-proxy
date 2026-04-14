<?php

declare(strict_types=1);

namespace Lararoxy\Contracts;

interface HealthChecker
{
    /**
     * Perform a health check against the given service URL.
     */
    public function check(string $serviceName, string $url): bool;

    /**
     * Get the current health status: 'healthy', 'unhealthy', or 'unknown'.
     */
    public function getStatus(string $serviceName): string;
}
