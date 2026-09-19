<?php

declare(strict_types=1);

namespace EzPhp\Authorization;

use Closure;
use EzPhp\Auth\UserInterface;
use EzPhp\Contracts\ContainerInterface;

/**
 * Class Gate
 *
 * Decides whether the current user may perform an ability, optionally on a subject.
 *
 * Resolution order for `allows($ability, $subject)`:
 *   1. No user → denied.
 *   2. `before()` callbacks, in registration order — the first non-null result wins.
 *   3. Subject is an object or class-string with a registered policy →
 *      the policy method named exactly like the ability (`$policy->update($user, $subject)`).
 *      A missing method denies.
 *   4. Otherwise the closure registered via `define()`; unknown abilities deny.
 *
 * Policies are registered explicitly with `policy()` (no naming convention or
 * discovery) and resolved from the container, so they may use constructor injection.
 *
 * @package EzPhp\Authorization
 */
final class Gate
{
    /** @var array<string, Closure> */
    private array $abilities = [];

    /** @var array<class-string, class-string> */
    private array $policies = [];

    /** @var list<Closure> */
    private array $before = [];

    /**
     * Gate Constructor
     *
     * @param ContainerInterface $container Resolves policy instances.
     * @param Closure(): ?UserInterface $userResolver Returns the acting user, or null for a guest.
     */
    public function __construct(
        private readonly ContainerInterface $container,
        private readonly Closure $userResolver,
    ) {
    }

    /**
     * Define a standalone ability.
     *
     * The callback receives the user followed by the subject (if any) and
     * returns bool.
     *
     * @param string $ability
     * @param callable $callback
     *
     * @return void
     */
    public function define(string $ability, callable $callback): void
    {
        $this->abilities[$ability] = Closure::fromCallable($callback);
    }

    /**
     * Map a subject class to its policy class.
     *
     * @param class-string $subjectClass
     * @param class-string $policyClass
     *
     * @return void
     */
    public function policy(string $subjectClass, string $policyClass): void
    {
        $this->policies[$subjectClass] = $policyClass;
    }

    /**
     * Register a callback that runs before every check.
     *
     * The callback receives (UserInterface $user, string $ability, mixed $subject)
     * and returns true (allow), false (deny) or null (continue with normal resolution).
     *
     * @param callable $callback
     *
     * @return void
     */
    public function before(callable $callback): void
    {
        $this->before[] = Closure::fromCallable($callback);
    }

    /**
     * Return true when the current user may perform the ability.
     *
     * @param string $ability
     * @param mixed $subject
     *
     * @return bool
     */
    public function allows(string $ability, mixed $subject = null): bool
    {
        $user = ($this->userResolver)();

        if ($user === null) {
            return false;
        }

        foreach ($this->before as $callback) {
            $result = $callback($user, $ability, $subject);

            if (is_bool($result)) {
                return $result;
            }
        }

        $policy = $this->resolvePolicy($subject);

        if ($policy !== null) {
            return $this->callPolicy($policy, $ability, $user, $subject);
        }

        if (!isset($this->abilities[$ability])) {
            return false;
        }

        $args = $subject === null ? [$user] : [$user, $subject];

        return ($this->abilities[$ability])(...$args) === true;
    }

    /**
     * Alias of allows().
     *
     * @param string $ability
     * @param mixed $subject
     *
     * @return bool
     */
    public function can(string $ability, mixed $subject = null): bool
    {
        return $this->allows($ability, $subject);
    }

    /**
     * Return true when the current user may NOT perform the ability.
     *
     * @param string $ability
     * @param mixed $subject
     *
     * @return bool
     */
    public function denies(string $ability, mixed $subject = null): bool
    {
        return !$this->allows($ability, $subject);
    }

    /**
     * Throw when the ability is denied.
     *
     * @param string $ability
     * @param mixed $subject
     *
     * @return void
     *
     * @throws AuthorizationException
     */
    public function authorize(string $ability, mixed $subject = null): void
    {
        if (!$this->allows($ability, $subject)) {
            throw AuthorizationException::forAbility($ability);
        }
    }

    /**
     * Return a copy of this gate that checks against the given user instead.
     *
     * Registered abilities, policies and before-callbacks are shared by value
     * at the time of the call.
     *
     * @param UserInterface $user
     *
     * @return self
     */
    public function forUser(UserInterface $user): self
    {
        $gate = new self($this->container, static fn (): UserInterface => $user);
        $gate->abilities = $this->abilities;
        $gate->policies = $this->policies;
        $gate->before = $this->before;

        return $gate;
    }

    /**
     * Find the policy class registered for the subject (object or class-string), if any.
     *
     * @param mixed $subject
     *
     * @return class-string|null
     */
    private function resolvePolicy(mixed $subject): ?string
    {
        if (is_object($subject)) {
            $class = $subject::class;
        } elseif (is_string($subject) && class_exists($subject)) {
            $class = $subject;
        } else {
            return null;
        }

        foreach ($this->policies as $subjectClass => $policyClass) {
            if (is_a($class, $subjectClass, true)) {
                return $policyClass;
            }
        }

        return null;
    }

    /**
     * @param class-string $policyClass
     * @param string $ability
     * @param UserInterface $user
     * @param mixed $subject
     *
     * @return bool
     */
    private function callPolicy(string $policyClass, string $ability, UserInterface $user, mixed $subject): bool
    {
        $policy = $this->container->make($policyClass);

        if (!method_exists($policy, $ability)) {
            return false;
        }

        // A class-string subject means "class-level" checks such as create: pass the string through.
        return $policy->{$ability}($user, $subject) === true;
    }
}
