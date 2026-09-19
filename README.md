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

```php
Route::get('/posts/create', $handler)
    ->middleware(new CanMiddleware($gate, 'create', Post::class));

// Per-request subject:
new CanMiddleware($gate, 'update', fn (RequestInterface $r): ?Post => Post::find($r->param('id')));
```

## Not included

Roles, permissions, database storage, and any change to `ez-php/auth` (its own `Auth::can()`/`hasRole()` RBAC hooks are independent).
