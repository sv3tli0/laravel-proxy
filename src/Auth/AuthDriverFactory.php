<?php

declare(strict_types=1);

namespace Lararoxy\Auth;

use Lararoxy\Contracts\AuthDriver;
use Lararoxy\Enums\AuthType;
use Lararoxy\Exceptions\InvalidConfigurationException;

class AuthDriverFactory
{
    /** @var array<string, class-string<AuthDriver>|callable> */
    protected array $customDrivers = [];

    public function make(array $config): AuthDriver
    {
        $type = AuthType::tryFrom($config['driver'] ?? '');

        return match ($type) {
            AuthType::Sanctum, AuthType::SanctumSpa => new SanctumAuthDriver($config['guard'] ?? 'sanctum'),
            AuthType::ApiKey => new ApiKeyAuthDriver($config['header'] ?? 'X-Api-Key'),
            AuthType::Passthrough => new PassthroughAuthDriver,
            AuthType::Custom => $this->resolveCustom($config),
            default => throw new InvalidConfigurationException(
                "Unsupported auth driver [{$config['driver']}]."
            ),
        };
    }

    /**
     * Register a custom auth driver.
     */
    public function extend(string $name, string|callable $driver): void
    {
        $this->customDrivers[$name] = $driver;
    }

    protected function resolveCustom(array $config): AuthDriver
    {
        $class = $config['class'] ?? null;

        if ($class !== null && class_exists($class)) {
            $instance = app($class);

            if ($instance instanceof AuthDriver) {
                return $instance;
            }
        }

        throw new InvalidConfigurationException(
            'Custom auth driver requires a valid "class" implementing AuthDriver.'
        );
    }
}
