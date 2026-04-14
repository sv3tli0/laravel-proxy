<?php

declare(strict_types=1);

namespace Lararoxy\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Lararoxy\Support\ConfigRegistry;

#[Signature('lararoxy:services')]
#[Description('List all registered upstream and outgoing services')]
class ListServicesCommand extends Command
{
    public function handle(ConfigRegistry $registry): int
    {
        $services = $registry->allServices();
        $outgoing = $registry->allOutgoing();

        if (empty($services) && empty($outgoing)) {
            $this->warn('No services registered.');

            return self::SUCCESS;
        }

        if (! empty($services)) {
            $this->info('Upstream Services:');
            $rows = [];

            foreach ($services as $service) {
                $rows[] = [
                    $service->name,
                    $service->baseUrl,
                    $service->timeout.'s',
                    $service->retry ? "{$service->retry->times}x" : '-',
                    $service->circuitBreaker?->enabled ? "threshold:{$service->circuitBreaker->threshold}" : '-',
                ];
            }

            $this->table(['Name', 'Base URL', 'Timeout', 'Retry', 'Circuit Breaker'], $rows);
        }

        if (! empty($outgoing)) {
            $this->newLine();
            $this->info('Outgoing Services:');
            $rows = [];

            foreach ($outgoing as $service) {
                $rows[] = [
                    $service->name,
                    $service->baseUrl,
                    $service->timeout.'s',
                    $service->tracking ? $service->tracking->store : '-',
                    $service->callback ? $service->callback->path : '-',
                ];
            }

            $this->table(['Name', 'Base URL', 'Timeout', 'Tracking', 'Callback Path'], $rows);
        }

        return self::SUCCESS;
    }
}
