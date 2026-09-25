# ez-php/authorization

Gate and policy based authorization for the ez-php framework. Answers "may this user do X (to Y)?" on top of `ez-php/auth`. It has no role or permission schema — you decide how abilities are computed.

## Installation

```bash
composer require ez-php/authorization
```

Register the provider in `provider/modules.php`:

```php
$app->register(\EzPhp\Authorization\AuthorizationServiceProvider::class);
```

The `Gate` is bound as a container singleton and checks against `Auth::user()`. Guests are always denied.

## Abilities and policies

Register from your own provider's `boot()`:

```php
$gate = $app->make(Gate::class);

// Standalone ability: callback(UserInterface $user, mixed $subject = …): bool
$gate->define('view-admin', fn (User $u): bool => $u->isAdmin());

// Policy: explicit subject class → policy class (no naming convention, no discovery)
$gate->policy(Post::class, PostPolicy::class);

// Runs before every check; true = allow, false = deny, null = continue
$gate->before(fn (User $u, string $ability): ?bool => $u->isAdmin() ? true : null);
```

A policy is a plain class resolved from the container. The method named like the ability is called with `($user, $subject)`; a missing method denies.

```php
final class PostPolicy
{
    public function update(User $user, Post $post): bool
    {
        return $user->getAuthId() === $post->ownerId;
    }

    public function create(User $user, string $class): bool // class-level check: subject is a class-string
    {
        return true;
    }
}
```

## Checking

```php
$gate->allows('update', $post);    // bool  (can() is an alias)
$gate->denies('update', $post);    // bool
$gate->authorize('update', $post); // throws AuthorizationException (code 403)
$gate->forUser($other)->allows(…); // check for a different user
```

Policy lookup accepts subclasses of the registered class. Only strict `true` results allow.

## Middleware

Place after `AuthMiddleware`; denied requests get `403`.

Register an alias once, then attach it with the ability — and optionally a string
subject — as parameters (`can:ability[,subject]`). The container builds `CanMiddleware`
with the bound `Gate`; the parameters are passed on each request:

```php
use EzPhp\Authorization\CanMiddleware;

// before bootstrap (e.g. public/index.php)
$app->middlewareAlias('can', CanMiddleware::class);

// routes/web.php
$router->get('/posts/create', $handler)->middleware('can:create,App\Entities\Post'); // class-level policy check
$router->get('/dashboard', $handler)->middleware('can:view-dashboard');                // define()d ability, no subject
```

A string subject is handed to the Gate as-is, so it suits class-level checks (a
class-string selects the policy). Registrations stay plain strings, so `route:cache`
and `route:list` work unchanged.

For a subject resolved per request — the post named by a route parameter — construct
`CanMiddleware` with a resolver closure in a small wrapper middleware that receives
the `Gate` and your repository:

```php
use EzPhp\Authorization\CanMiddleware;
use EzPhp\Authorization\Gate;
use EzPhp\Contracts\MiddlewareInterface;
use EzPhp\Http\RequestInterface;
use EzPhp\Http\ResponseInterface;

final class CanUpdatePost implements MiddlewareInterface
{
    public function __construct(private readonly Gate $gate, private readonly PostRepository $posts)
    {
    }

    public function handle(RequestInterface $request, callable $next): ResponseInterface
    {
        $subject = fn (RequestInterface $r): ?object => $this->posts->find((int) $r->param('id'));

        return (new CanMiddleware($this->gate, 'update', $subject))->handle($request, $next);
    }
}

$router->put('/posts/{id}', $handler)->middleware(CanUpdatePost::class);
```

## Not included

Roles, permissions, database storage, and any change to `ez-php/auth` (its own `Auth::can()`/`hasRole()` RBAC hooks are independent).
