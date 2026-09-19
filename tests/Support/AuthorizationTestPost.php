<?php

declare(strict_types=1);

namespace Tests\Support;

/**
 * Test subject owned by a user id.
 */
class AuthorizationTestPost
{
    public function __construct(public readonly int $ownerId)
    {
    }
}
