<?php

declare(strict_types=1);

namespace EzPhp\Authorization;

use Closure;
use EzPhp\Contracts\ParameterizedMiddlewareInterface;
use EzPhp\Http\RequestInterface;
use EzPhp\Http\Response;
use EzPhp\Http\ResponseInterface;
use LogicException;

/**
 * Class CanMiddleware
 *
 * Route middleware that requires the current user to hold an ability.
 * Place it after `AuthMiddleware`. Denied requests (including guests) get 403.
 *
 * Register it by name with parameters — `ability[,subject]` — and let the
 * container build it (only the Gate is injected):
 *
 *   $app->middlewareAlias('can', CanMiddleware::class);
 *   $router->get('/posts/create', $handler)->middleware('can:create,App\Entities\Post');
 *   $router->get('/dashboard', $handler)->middleware('can:view-dashboard');
 *
 * A string subject is passed to the Gate as-is (a class-string selects the
 * policy for class-level checks). For a per-request subject (e.g. the post
 * named by a route parameter), construct it with a resolver in a small wrapper
 * middleware instead — see README "Middleware":
 *
 *   new CanMiddleware($this->gate, 'update', fn (RequestInterface $r): ?object => $this->posts->find((int) $r->param('id')))
 *
 * Registration parameters take precedence over constructor values.
 *
 * @package EzPhp\Authorization
 */
final class CanMiddleware implements ParameterizedMiddlewareInterface
{
    /**
     * CanMiddleware Constructor
     *
     * @param Gate $gate
     * @param string|null $ability Ability to check; may instead come from the `can:ability` registration.
     * @param string|Closure(RequestInterface): mixed|null $subject Static subject (e.g. class-string) or a resolver run per request.
     */
    public function __construct(
        private readonly Gate $gate,
        private readonly ?string $ability = null,
        private readonly string|Closure|null $subject = null,
    ) {
    }

    /**
     * @param RequestInterface $request
     * @param callable(RequestInterface): ResponseInterface $next
     * @param string ...$parameters `ability` and optional `subject` from a `can:ability,subject` registration.
     *
     * @return ResponseInterface
     *
     * @throws LogicException When no ability is configured, or more than two parameters are given.
     */
    public function handle(RequestInterface $request, callable $next, string ...$parameters): ResponseInterface
    {
        if (count($parameters) > 2) {
            throw new LogicException(sprintf(
                "CanMiddleware takes at most two parameters ('can:ability,subject'), %d given.",
                count($parameters),
            ));
        }

        $ability = $parameters[0] ?? $this->ability;

        if ($ability === null || $ability === '') {
            throw new LogicException(
                "CanMiddleware has no ability: register it as 'can:ability[,subject]' or pass one to the constructor."
            );
        }

        $configured = $parameters[1] ?? $this->subject;
        $subject = $configured instanceof Closure ? $configured($request) : $configured;

        if ($this->gate->denies($ability, $subject)) {
            return (new Response('Forbidden', 403))
                ->withHeader('Content-Type', 'text/plain; charset=UTF-8');
        }

        return $next($request);
    }
}
