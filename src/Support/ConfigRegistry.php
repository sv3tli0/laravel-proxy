<?php

declare(strict_types=1);

namespace Lararoxy\Support;

use Lararoxy\Data\GroupDefinition;
use Lararoxy\Data\OutgoingServiceDefinition;
use Lararoxy\Data\ServiceDefinition;
use Lararoxy\Exceptions\ServiceNotFoundException;

class ConfigRegistry
{
    /** @var array<string, ServiceDefinition> */
    protected array $services = [];

    /** @var array<string, GroupDefinition> */
    protected array $groups = [];

    /** @var array<string, OutgoingServiceDefinition> */
    protected array $outgoing = [];

    protected bool $booted = false;

    public function __construct(
        protected AttributeScanner $scanner,
    ) {}

    /**
     * Boot the registry by merging all three config sources.
     *
     * Precedence: Attributes > Fluent API > Config file
     */
    public function boot(array $config): void
    {
        if ($this->booted) {
            return;
        }

        // 1. Config file (lowest priority — loaded first, overridden by later sources)
        $this->loadFromConfig($config);

        // 2. Attribute scanning (highest priority — overwrites config entries)
        $scanPaths = $config['attributes']['scan_paths'] ?? [];

        if (($config['attributes']['enabled'] ?? true) && ! empty($scanPaths)) {
            $this->scanner->scan($scanPaths);

            foreach ($this->scanner->getServices() as $name => $service) {
                $this->services[$name] = $service;
            }

            foreach ($this->scanner->getGroups() as $name => $group) {
                $this->groups[$name] = $group;
            }

            foreach ($this->scanner->getOutgoing() as $name => $outgoing) {
                $this->outgoing[$name] = $outgoing;
            }
        }

        // 3. Fluent API registrations are applied later via registerService/registerGroup/registerOutgoing
        //    (called during route file loading, which happens after boot)

        $this->booted = true;
    }

    /**
     * Register a service from fluent API (overwrites attribute/config).
     */
    public function registerService(ServiceDefinition $service): void
    {
        $this->services[$service->name] = $service;
    }

    /**
     * Register a group from fluent API (overwrites attribute/config).
     */
    public function registerGroup(GroupDefinition $group): void
    {
        $this->groups[$group->name] = $group;
    }

    /**
     * Register an outgoing service from fluent API (overwrites attribute/config).
     */
    public function registerOutgoing(OutgoingServiceDefinition $outgoing): void
    {
        $this->outgoing[$outgoing->name] = $outgoing;
    }

    public function getService(string $name): ServiceDefinition
    {
        return $this->services[$name]
            ?? throw new ServiceNotFoundException("Upstream service [{$name}] is not configured.");
    }

    public function getGroup(string $name): GroupDefinition
    {
        return $this->groups[$name]
            ?? throw new ServiceNotFoundException("Endpoint group [{$name}] is not configured.");
    }

    public function getOutgoing(string $name): OutgoingServiceDefinition
    {
        return $this->outgoing[$name]
            ?? throw new ServiceNotFoundException("Outgoing service [{$name}] is not configured.");
    }

    public function hasService(string $name): bool
    {
        return isset($this->services[$name]);
    }

    public function hasGroup(string $name): bool
    {
        return isset($this->groups[$name]);
    }

    public function hasOutgoing(string $name): bool
    {
        return isset($this->outgoing[$name]);
    }

    /** @return array<string, ServiceDefinition> */
    public function allServices(): array
    {
        return $this->services;
    }

    /** @return array<string, GroupDefinition> */
    public function allGroups(): array
    {
        return $this->groups;
    }

    /** @return array<string, OutgoingServiceDefinition> */
    public function allOutgoing(): array
    {
        return $this->outgoing;
    }

    protected function loadFromConfig(array $config): void
    {
        foreach ($config['services'] ?? [] as $name => $serviceConfig) {
            $this->services[$name] = ServiceDefinition::fromArray($name, $serviceConfig);
        }

        foreach ($config['groups'] ?? [] as $name => $groupConfig) {
            $this->groups[$name] = GroupDefinition::fromArray($name, $groupConfig);
        }

        foreach ($config['outgoing'] ?? [] as $name => $outgoingConfig) {
            $this->outgoing[$name] = OutgoingServiceDefinition::fromArray($name, $outgoingConfig);
        }
    }
}
