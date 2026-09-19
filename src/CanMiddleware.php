<?php

declare(strict_types=1);

namespace EzPhp\Authorization;

use Closure;
use EzPhp\Contracts\MiddlewareInterface;
use EzPhp\Http\RequestInterface;
use EzPhp\Http\Response;
use EzPhp\Http\ResponseInterface;

/**
 * Class CanMiddleware
 *
 * Route middleware that requires the current user to hold an ability.
 * Place it after `AuthMiddleware`. Denied requests (including guests) get 403.
 *
 *   Route::get('/posts/create', $handler)
 *       ->middleware(new CanMiddleware($gate, 'create', Post::class));
 *
 * For per-request subjects pass a resolver:
 *
 *   new CanMiddleware($gate, 'update', fn (RequestInterface $r): ?Post => Post::find($r->param('id')))
 *
 * @package EzPhp\Authorization
 */
final class CanMiddleware implements MiddlewareInterface
{
    /**
     * CanMiddleware Constructor
     *
     * @param Gate $gate
     * @param string $ability
     * @param string|Closure(RequestInterface): mixed|null $subject Static subject (e.g. class-string) or a resolver run per request.
     */
    public function __construct(
        private readonly Gate $gate,
        private readonly string $ability,
        private readonly string|Closure|null $subject = null,
    ) {
    }

    /**
     * @param RequestInterface $request
     * @param callable(RequestInterface): ResponseInterface $next
     *
     * @return ResponseInterface
     */
    public function handle(RequestInterface $request, callable $next): ResponseInterface
    {
        $subject = $this->subject instanceof Closure ? ($this->subject)($request) : $this->subject;

        if ($this->gate->denies($this->ability, $subject)) {
            return (new Response('Forbidden', 403))
                ->withHeader('Content-Type', 'text/plain; charset=UTF-8');
        }

        return $next($request);
    }
}
