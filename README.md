# contenir/cache

Framework-agnostic page-cache control for [Contenir CMS](https://github.com/contenir).

The CMS lets an operator toggle page caching on or off, decide which request signals participate in cache-key generation (query/post/session/files/cookie), and exclude specific URL patterns. The consuming Site (Mezzio, Laminas MVC, anything else) reads those settings on every request and applies them in its caching layer.

This package provides the *domain* — an immutable state value plus a repository interface, with file-based and in-memory implementations. Framework-specific listeners come from sibling packages (e.g. `contenir/cache-laminas-mvc`).

## Install

```bash
composer require contenir/cache
```

Add `contenir/config` if you intend to use `Repository\FileRepository` (admin-side writer). Sites that read state from the merged Laminas/Mezzio config — and use `Repository\InMemoryRepository` in tests — don't need it.

## Usage

### Reading state

```php
use Contenir\Cache\Repository\FileRepository;

$repo = new FileRepository('/var/www/shared/pagecache.local.php');
$state = $repo->get();

if ($state->enabled) {
    // Apply cache options ($state->options) and route overrides ($state->routes)
}
```

### Writing state (admin)

```php
use Contenir\Cache\CacheControl;

$repo->save(new CacheControl(
    enabled: true,
    options: ['cache_with_query' => true, 'cache_with_session' => false],
    routes:  ['/api.*' => ['cache' => false]],
));
```

### File format

`FileRepository` reads and writes a PHP file under the `pagecache` namespace, so the same file can be merged directly into a Laminas/Mezzio site config:

```php
<?php

return [
    'pagecache' => [
        'options' => [
            'cache'              => true,
            'cache_with_query'   => true,
            'cache_with_session' => false,
        ],
        'routes' => [
            '/api.*' => ['cache' => false],
        ],
    ],
];
```

A missing or unreadable file resolves to disabled state with empty options and routes, so first-run consumers never serve cached content before the admin has explicitly opted in. Other top-level config keys (`errors`, `maintenance`, …) and operator-authored sibling options are preserved on save.

### Testing

`InMemoryRepository` is shipped in `src/` so consumers can use it in their own test suites:

```php
use Contenir\Cache\Repository\InMemoryRepository;
use Contenir\Cache\CacheControl;

$repo = new InMemoryRepository(CacheControl::enabled());
```
