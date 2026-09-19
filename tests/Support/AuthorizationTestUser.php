<?php

declare(strict_types=1);

namespace Tests\Support;

use EzPhp\Auth\UserInterface;

/**
 * Test user.
 */
final readonly class AuthorizationTestUser implements UserInterface
{
    public function __construct(public int $id, public bool $admin = false)
    {
    }

    public function getAuthId(): int
    {
        return $this->id;
    }

    public function getAuthPassword(): string
    {
        return 'hash';
    }
}
