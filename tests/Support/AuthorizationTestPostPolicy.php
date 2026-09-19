<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Test policy.
 */
final class AuthorizationTestPostPolicy
{
    public function update(AuthorizationTestUser $user, AuthorizationTestPost $post): bool
    {
        return $user->id === $post->ownerId;
    }

    public function create(AuthorizationTestUser $user, string $class): bool
    {
        return $class === AuthorizationTestPost::class && $user->id > 0;
    }
}
