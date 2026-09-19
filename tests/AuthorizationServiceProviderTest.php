<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Auth\Auth;
use EzPhp\Authorization\AuthorizationServiceProvider;
use EzPhp\Authorization\Gate;
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
}
