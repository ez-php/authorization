<?php

declare(strict_types=1);

namespace Tests\Support;

use EzPhp\Contracts\ContainerInterface;
use RuntimeException;

/**
 * Minimal ContainerInterface stub: autowires zero-argument classes, honours bind()/instance().
 */
final class AuthorizationFakeContainer implements ContainerInterface
{
    /** @var array<string, callable(ContainerInterface): object> */
    private array $bindings = [];

    /** @var array<string, object> */
    private array $instances = [];

    public function bind(string $abstract, string|callable|null $factory = null): static
    {
        if (is_callable($factory)) {
            $this->bindings[$abstract] = $factory;
        }

        return $this;
    }

    /**
     * @template T of object
     * @param class-string<T> $abstract
     * @return T
     */
    public function make(string $abstract): mixed
    {
        $result = $this->instances[$abstract]
            ?? (isset($this->bindings[$abstract]) ? ($this->bindings[$abstract])($this) : null)
            ?? (class_exists($abstract) ? new $abstract() : null);

        if (!$result instanceof $abstract) {
            throw new RuntimeException("No binding for {$abstract}");
        }

        return $result;
    }

    public function instance(string $abstract, object $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    public function has(string $abstract): bool
    {
        return isset($this->instances[$abstract]) || isset($this->bindings[$abstract]);
    }

    public function wasBound(string $abstract): bool
    {
        return isset($this->bindings[$abstract]);
    }
}
