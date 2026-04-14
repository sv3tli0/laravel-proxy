<?php

declare(strict_types=1);

namespace Lararoxy\Console;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Lararoxy\Support\ConfigRegistry;

#[Signature('lararoxy:routes {group? : Filter by group name}')]
#[Description('List all registered proxy routes')]
class ListRoutesCommand extends Command
{
    public function handle(ConfigRegistry $registry): int
    {
        $filterGroup = $this->argument('group');
        $groups = $registry->allGroups();

        if (empty($groups)) {
            $this->warn('No proxy routes registered.');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($groups as $group) {
            if ($filterGroup !== null && $group->name !== $filterGroup) {
                continue;
            }

            foreach ($group->routes as $route) {
                $rows[] = [
                    $group->name,
                    $route->method,
                    $group->prefix.$route->path,
                    $route->upstream ?? 'aggregate',
                    $route->upstreamPath ?? ($route->aggregate ? 'fan-out' : '-'),
                    $route->upstreamMethod ?? $route->method,
                ];
            }
        }

        if (empty($rows)) {
            $this->warn($filterGroup
                ? "No routes found for group [{$filterGroup}]."
                : 'No proxy routes registered.');

            return self::SUCCESS;
        }

        $this->table(
            ['Group', 'Method', 'Path', 'Upstream', 'Upstream Path', 'Upstream Method'],
            $rows,
        );

        return self::SUCCESS;
    }
}
