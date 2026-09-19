<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Auth\UserInterface;
use EzPhp\Authorization\AuthorizationException;
use EzPhp\Authorization\Gate;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\Support\AuthorizationFakeContainer;
use Tests\Support\AuthorizationTestDraftPost;
use Tests\Support\AuthorizationTestPost;
use Tests\Support\AuthorizationTestPostPolicy;
use Tests\Support\AuthorizationTestUser;

#[CoversClass(Gate::class)]
#[UsesClass(AuthorizationException::class)]
final class AuthorizationGateTest extends TestCase
{
    private function gate(?UserInterface $user): Gate
    {
        return new Gate(new AuthorizationFakeContainer(), static fn (): ?UserInterface => $user);
    }

    public function testGuestIsAlwaysDenied(): void
    {
        $gate = $this->gate(null);
        $gate->define('anything', static fn (): bool => true);
        $gate->before(static fn (): bool => true);

        self::assertFalse($gate->allows('anything'));
        self::assertTrue($gate->denies('anything'));
    }

    public function testDefinedAbilityReceivesUserAndSubject(): void
    {
        $gate = $this->gate(new AuthorizationTestUser(1));
        $gate->define('own', static fn (AuthorizationTestUser $u, AuthorizationTestPost $p): bool => $u->id === $p->ownerId);
        $gate->define('plain', static fn (AuthorizationTestUser $u): bool => $u->id === 1);

        self::assertTrue($gate->can('plain'));
        self::assertTrue($gate->allows('own', new AuthorizationTestPost(1)));
        self::assertFalse($gate->allows('own', new AuthorizationTestPost(2)));
    }

    public function testUnknownAbilityIsDenied(): void
    {
        self::assertFalse($this->gate(new AuthorizationTestUser(1))->allows('nope'));
    }

    public function testNonBooleanAbilityResultIsDenied(): void
    {
        $gate = $this->gate(new AuthorizationTestUser(1));
        $gate->define('loose', static fn (): int => 1);

        self::assertFalse($gate->allows('loose'));
    }

    public function testPolicyResolvedForObjectAndSubclass(): void
    {
        $gate = $this->gate(new AuthorizationTestUser(1));
        $gate->policy(AuthorizationTestPost::class, AuthorizationTestPostPolicy::class);

        self::assertTrue($gate->allows('update', new AuthorizationTestPost(1)));
        self::assertFalse($gate->allows('update', new AuthorizationTestPost(2)));
        self::assertTrue($gate->allows('update', new AuthorizationTestDraftPost(1)));
    }

    public function testPolicyResolvedForClassStringSubject(): void
    {
        $gate = $this->gate(new AuthorizationTestUser(1));
        $gate->policy(AuthorizationTestPost::class, AuthorizationTestPostPolicy::class);

        self::assertTrue($gate->allows('create', AuthorizationTestPost::class));
    }

    public function testMissingPolicyMethodDenies(): void
    {
        $gate = $this->gate(new AuthorizationTestUser(1));
        $gate->policy(AuthorizationTestPost::class, AuthorizationTestPostPolicy::class);

        self::assertFalse($gate->allows('delete', new AuthorizationTestPost(1)));
    }

    public function testSubjectWithoutPolicyFallsBackToDefinedAbility(): void
    {
        $gate = $this->gate(new AuthorizationTestUser(1));
        $gate->define('update', static fn (): bool => true);

        self::assertTrue($gate->allows('update', new AuthorizationTestPost(9)));
    }

    public function testBeforeCallbackCanAllowOrDenyAndNullContinues(): void
    {
        $admin = $this->gate(new AuthorizationTestUser(1, admin: true));
        $admin->policy(AuthorizationTestPost::class, AuthorizationTestPostPolicy::class);
        $admin->before(static fn (AuthorizationTestUser $u): ?bool => $u->admin ? true : null);

        self::assertTrue($admin->allows('update', new AuthorizationTestPost(2)));

        $user = $this->gate(new AuthorizationTestUser(1));
        $user->policy(AuthorizationTestPost::class, AuthorizationTestPostPolicy::class);
        $user->before(static fn (AuthorizationTestUser $u): ?bool => $u->admin ? true : null);

        self::assertFalse($user->allows('update', new AuthorizationTestPost(2)));
        self::assertTrue($user->allows('update', new AuthorizationTestPost(1)));

        $user->before(static fn (): bool => false);
        self::assertFalse($user->allows('update', new AuthorizationTestPost(1)));
    }

    public function testAuthorizeThrowsWhenDeniedAndPassesWhenAllowed(): void
    {
        $gate = $this->gate(new AuthorizationTestUser(1));
        $gate->define('yes', static fn (): bool => true);

        $gate->authorize('yes');

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionCode(403);
        $gate->authorize('no');
    }

    public function testForUserSwapsUserAndKeepsRegistrations(): void
    {
        $gate = $this->gate(null);
        $gate->policy(AuthorizationTestPost::class, AuthorizationTestPostPolicy::class);
        $gate->define('flag', static fn (AuthorizationTestUser $u): bool => $u->admin);

        $other = $gate->forUser(new AuthorizationTestUser(5, admin: true));

        self::assertTrue($other->allows('flag'));
        self::assertTrue($other->allows('update', new AuthorizationTestPost(5)));
        self::assertFalse($gate->allows('flag'));
    }
}
