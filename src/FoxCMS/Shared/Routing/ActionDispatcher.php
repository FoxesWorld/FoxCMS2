<?php

declare(strict_types=1);

namespace FoxCMS\Shared\Routing;

use Closure;
use InvalidArgumentException;
use LogicException;
use OutOfBoundsException;

/**
 * Small transport-agnostic action registry.
 *
 * Compatibility transports (legacy form RPC, admin RPC, CLI bridges) can keep
 * their public action names while registering cohesive handlers from separate
 * modules. The dispatcher intentionally knows nothing about HTTP or responses.
 */
final class ActionDispatcher
{
    /** @var array<string, Closure(): mixed> */
    private array $handlers = [];

    /** @var array<string, array<string, scalar|null>> */
    private array $metadata = [];

    /** @param array<string, scalar|null> $metadata */
    public function register(string $action, callable $handler, array $metadata = []): self
    {
        $action = trim($action);
        if (preg_match('/^[A-Za-z][A-Za-z0-9_.:-]{0,95}$/D', $action) !== 1) {
            throw new InvalidArgumentException('Invalid action name: ' . $action);
        }
        if (isset($this->handlers[$action])) {
            throw new LogicException('Duplicate action registration: ' . $action);
        }

        $this->handlers[$action] = Closure::fromCallable($handler);
        $this->metadata[$action] = $metadata;
        return $this;
    }

    public function has(string $action): bool
    {
        return isset($this->handlers[$action]);
    }

    /** @return list<string> */
    public function actions(): array
    {
        return array_keys($this->handlers);
    }

    /** @return array<string, scalar|null> */
    public function metadata(string $action): array
    {
        return $this->metadata[$action] ?? [];
    }

    public function dispatch(string $action): mixed
    {
        $handler = $this->handlers[$action] ?? null;
        if (!$handler instanceof Closure) {
            throw new OutOfBoundsException('Action is not registered: ' . $action);
        }
        return $handler();
    }
}
