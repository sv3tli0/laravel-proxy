<?php

declare(strict_types=1);

namespace Lararoxy\Builders;

use Closure;

class WebhookGroupBuilder
{
    protected string $prefix = '';

    /** @var array<WebhookBuilder> */
    protected array $webhooks = [];

    public function __construct(Closure $callback)
    {
        $callback($this);
    }

    public function webhook(string $path): WebhookBuilder
    {
        $builder = new WebhookBuilder($path);
        $this->webhooks[] = $builder;

        return $builder;
    }

    public function prefix(string $prefix): static
    {
        $this->prefix = $prefix;

        return $this;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /** @return array<WebhookBuilder> */
    public function getWebhooks(): array
    {
        return $this->webhooks;
    }
}
