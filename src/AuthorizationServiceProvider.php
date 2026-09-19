<?php

declare(strict_types=1);

namespace EzPhp\Authorization;

use EzPhp\Auth\Auth;
use EzPhp\Contracts\ContainerInterface;
use EzPhp\Contracts\ServiceProvider;

/**
 * Class AuthorizationServiceProvider
 *
 * Binds the Gate as a container singleton, resolving the acting user via
 * `Auth::user()`. Register in provider/modules.php:
 *
 *   $app->register(AuthorizationServiceProvider::class);
 *
 * Then register policies/abilities from your own provider's boot():
 *
 *   $app->make(Gate::class)->policy(Post::class, PostPolicy::class);
 *
 * @package EzPhp\Authorization
 */
final class AuthorizationServiceProvider extends ServiceProvider
{
    /**
     * Bind the Gate.
     */
    public function register(): void
    {
        $this->app->bind(
            Gate::class,
            fn (ContainerInterface $app): Gate => new Gate($app, static fn () => Auth::user()),
        );
    }

    /**
     * Nothing to boot — policies are registered by the application.
     */
    public function boot(): void
    {
    }
}
