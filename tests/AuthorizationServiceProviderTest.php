<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Auth\Auth;
use EzPhp\Authorization\AuthorizationServiceProvider;
use EzPhp\Authorization\Gate;
use EzPhp\Contracts\GateInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\Support\AuthorizationFakeContainer;
use Tests\Support\AuthorizationTestUser;

#[CoversClass(AuthorizationServiceProvider::class)]
#[UsesClass(Gate::class)]
final class AuthorizationServiceProviderTest extends TestCase
{
    protected function tearDown(): void
    {
        Auth::resetInstance();
    }

    public function testBindsGateResolvingTheAuthUser(): void
    {
        $container = new AuthorizationFakeContainer();
        $provider = new AuthorizationServiceProvider($container);
        $provider->register();
        $provider->boot();

        self::assertTrue($container->wasBound(Gate::class));

        $gate = $container->make(Gate::class);
        self::assertInstanceOf(Gate::class, $gate);
        $gate->define('ping', static fn (): bool => true);

        self::assertFalse($gate->allows('ping'));

        Auth::setInstance(new Auth(null));
        Auth::login(new AuthorizationTestUser(1));

        self::assertTrue($gate->allows('ping'));
    }

    public function testBindsGateInterfaceToTheSameGate(): void
    {
        $container = new AuthorizationFakeContainer();
        (new AuthorizationServiceProvider($container))->register();

        self::assertTrue($container->wasBound(GateInterface::class));

        // Resolved through make(Gate::class), so the framework container's singleton cache
        // hands out the one Gate the application registered its policies on.
        $gate = new Gate($container, static fn () => null);
        $container->instance(Gate::class, $gate);
        self::assertSame($gate, $container->make(GateInterface::class));
    }
}
