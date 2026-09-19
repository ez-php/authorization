<?php

declare(strict_types=1);

namespace EzPhp\Authorization;

use EzPhp\Contracts\EzPhpException;

/**
 * Class AuthorizationException
 *
 * Thrown by Gate::authorize() when an ability is denied.
 *
 * The module keeps no dependency on the framework core, so it does not extend
 * the framework's HttpException. Applications that want a 403 page map this
 * exception in their exception handler; CanMiddleware already answers 403 itself.
 *
 * @package EzPhp\Authorization
 */
final class AuthorizationException extends EzPhpException
{
    /**
     * @param string $ability
     *
     * @return self
     */
    public static function forAbility(string $ability): self
    {
        return new self("This action is unauthorized: {$ability}", 403);
    }
}
