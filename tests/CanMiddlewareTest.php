<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Auth\UserInterface;
use EzPhp\Authorization\CanMiddleware;
use EzPhp\Authorization\Gate;
use EzPhp\Contracts\ParameterizedMiddlewareInterface;
use EzPhp\Http\Request;
use EzPhp\Http\RequestInterface;
use EzPhp\Http\Response;
use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\Support\AuthorizationFakeContainer;
use Tests\Support\AuthorizationTestPost;
use Tests\Support\AuthorizationTestPostPolicy;
use Tests\Support\AuthorizationTestUser;

#[CoversClass(CanMiddleware::class)]
#[UsesClass(Gate::class)]
final class CanMiddlewareTest extends TestCase
{
    private function gate(?UserInterface $user): Gate
    {
        $gate = new Gate(new AuthorizationFakeContainer(), static fn (): ?UserInterface => $user);
        $gate->policy(AuthorizationTestPost::class, AuthorizationTestPostPolicy::class);

        return $gate;
    }

    private function statusOf(CanMiddleware $middleware): int
    {
        return $middleware
            ->handle(new Request('GET', '/posts'), fn (): Response => new Response('ok', 200))
            ->status();
    }

    public function testAllowsWithClassStringSubject(): void
    {
        $mw = new CanMiddleware($this->gate(new AuthorizationTestUser(1)), 'create', AuthorizationTestPost::class);

        self::assertSame(200, $this->statusOf($mw));
    }

    public function testGuestGets403(): void
    {
        $mw = new CanMiddleware($this->gate(null), 'create', AuthorizationTestPost::class);

        self::assertSame(403, $this->statusOf($mw));
    }

    public function testResolverSubjectIsEvaluatedPerRequest(): void
    {
        $gate = $this->gate(new AuthorizationTestUser(1));

        $own = new CanMiddleware($gate, 'update', static fn (RequestInterface $r): AuthorizationTestPost => new AuthorizationTestPost(1));
        $foreign = new CanMiddleware($gate, 'update', static fn (RequestInterface $r): AuthorizationTestPost => new AuthorizationTestPost(2));

        self::assertSame(200, $this->statusOf($own));
        self::assertSame(403, $this->statusOf($foreign));
    }

    /**
     * @param string ...$parameters
     */
    private function statusWithParameters(CanMiddleware $middleware, string ...$parameters): int
    {
        return $middleware
            ->handle(new Request('GET', '/posts'), fn (): Response => new Response('ok', 200), ...$parameters)
            ->status();
    }

    public function testIsAParameterizedMiddleware(): void
    {
        self::assertInstanceOf(ParameterizedMiddlewareInterface::class, new CanMiddleware($this->gate(null)));
    }

    public function testAbilityAndSubjectCanComeFromRegistrationParameters(): void
    {
        $user = new CanMiddleware($this->gate(new AuthorizationTestUser(1)));
        $guest = new CanMiddleware($this->gate(null));

        self::assertSame(200, $this->statusWithParameters($user, 'create', AuthorizationTestPost::class));
        self::assertSame(403, $this->statusWithParameters($guest, 'create', AuthorizationTestPost::class));
    }

    public function testRegistrationParametersOverrideConstructorValues(): void
    {
        // Constructor would deny (unknown ability); the 'can:create,...' parameters allow.
        $mw = new CanMiddleware($this->gate(new AuthorizationTestUser(1)), 'undefined-ability');

        self::assertSame(200, $this->statusWithParameters($mw, 'create', AuthorizationTestPost::class));
    }

    public function testAbilityOnlyParameterChecksWithoutASubject(): void
    {
        $gate = $this->gate(new AuthorizationTestUser(1));
        $gate->define('view-dashboard', static fn (): bool => true);

        self::assertSame(200, $this->statusWithParameters(new CanMiddleware($gate), 'view-dashboard'));
    }

    public function testMissingAbilityIsAConfigurationError(): void
    {
        $this->expectException(LogicException::class);

        $this->statusOf(new CanMiddleware($this->gate(new AuthorizationTestUser(1))));
    }

    public function testMoreThanTwoParametersIsAConfigurationError(): void
    {
        $this->expectException(LogicException::class);

        $this->statusWithParameters(new CanMiddleware($this->gate(null)), 'update', AuthorizationTestPost::class, 'extra');
    }
}
