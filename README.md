# contenir/contenir-cache

Formerly `contenir/cache`; the old package is abandoned in favour of this one.

[![Continuous Integration](https://github.com/contenir/contenir-cache/actions/workflows/continuous-integration.yml/badge.svg)](https://github.com/contenir/contenir-cache/actions/workflows/continuous-integration.yml)
[![codecov](https://codecov.io/gh/contenir/contenir-cache/graph/badge.svg)](https://codecov.io/gh/contenir/contenir-cache)

Framework-agnostic page-cache control for [Contenir CMS](https://github.com/contenir).

The CMS lets an operator toggle page caching on or off, decide which request signals participate in cache-key generation (query/post/session/files/cookie), and exclude specific URL patterns. The consuming Site (Mezzio, Laminas MVC, anything else) reads those settings on every request and applies them in its caching layer.

This package provides the *domain* — an immutable state value plus a repository interface, with file-based and in-memory implementations. Framework-specific listeners come from sibling packages (e.g. `contenir/contenir-cache-laminas-mvc`).

## Install

```bash
composer require contenir/contenir-cache
```

Requires PHP 8.3, 8.4 or 8.5. The 0.x releases, which support PHP 8.1, remain
available from the `0.x` branch and `v0.*` tags; see [UPGRADE-2.0.md](UPGRADE-2.0.md).

`contenir/contenir-config` (`^2.1`), which `Repository\FileRepository` uses to read and write the state file, is installed with it.

## Usage

The public API is four types:

| Type | Purpose |
| --- | --- |
| `CacheControl` | Immutable state: `bool $enabled`, `array $options`, `array $routes`. `CacheControl::enabled()` and `CacheControl::disabled()` build the empty variants. |
| `CacheControlRepositoryInterface` | `get(): CacheControl` and `save(CacheControl): void`. `get()` never throws; `save()` throws `RuntimeException` when state cannot be persisted. |
| `Repository\FileRepository` | Reads and writes a PHP-array config file (via `contenir/contenir-config`). |
| `Repository\InMemoryRepository` | Holds state in memory, for tests. Starts disabled unless given an initial state. |

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

A missing or unreadable file resolves to disabled state with empty options and routes, so first-run consumers never serve cached content before the admin has explicitly opted in. When reading, `pagecache.options.cache` becomes `$enabled` and is removed from `$options`; option entries without a string name, and route entries that are not a `pattern => [options]` pair, are skipped.

On save:

- `pagecache.options.cache` is always written from `$enabled`. A `cache` key inside `$options` is ignored.
- Each entry of `$options` is written under `pagecache.options`; operator-authored sibling options (such as `ttl` or `priority`) that the state does not mention are kept.
- `pagecache.routes` is replaced entirely by `$routes`.
- Other top-level config keys (`errors`, `maintenance`, …) are preserved.
- The write is atomic. Failures throw `Contenir\Config\Exception\WriteException`, a `RuntimeException`, whose message names "cache control".

### Testing

`InMemoryRepository` is shipped in `src/` so consumers can use it in their own test suites:

```php
use Contenir\Cache\Repository\InMemoryRepository;
use Contenir\Cache\CacheControl;

$repo = new InMemoryRepository(CacheControl::enabled());
```

## Development

The QA toolchain is [php-db/phpdb-qa-tools](https://github.com/php-db/phpdb-qa-tools).
[Mago](https://mago.carthage.software/) is a standalone binary, installed
separately (`brew install mago`).

```bash
composer check             # everything below
composer cs-check          # mago format --check && mago lint
composer static-analysis   # mago analyze
composer test              # unit suite: CacheControl and InMemoryRepository, no I/O
composer test-integration  # integration suite: FileRepository on a real temp directory
composer test-coverage     # both suites, clover.xml for Codecov
composer mutation-test     # Infection mutation testing over both suites
```

## License

MIT. See [LICENSE](LICENSE).
