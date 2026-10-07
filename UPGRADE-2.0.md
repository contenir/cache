# Upgrading from 0.x to 2.0

2.0 has the same public API as 0.1. The platform requirement changes, and two
edge cases in `Repository\FileRepository` now behave as documented.

| | 0.x | 2.0 |
| --- | --- | --- |
| PHP | ^8.1 | 8.3, 8.4 or 8.5 |
| `contenir/config` | suggested, ^0.1 | **required**: `contenir/contenir-config` ^2.1 |

`contenir/config` is now a hard dependency. `Repository\FileRepository` always
needed it at runtime, but 0.x only suggested it, so installing without it
failed on first write. Composer now installs it for you; if you already
require `contenir/config` yourself, switch to `contenir/contenir-config` `^2.1`.

To upgrade, update the constraint:

```bash
composer remove contenir/cache && composer require contenir/contenir-page-cache:^2.0@RC
```

`CacheControl`, `CacheControlRepositoryInterface`, `Repository\FileRepository`
and `Repository\InMemoryRepository` keep their signatures, but the namespace
moves from `Contenir\Cache\` to `Contenir\PageCache\`. Update imports as
described in [UPGRADE-page-cache.md](UPGRADE-page-cache.md).

## Behaviour changes

### A `cache` option no longer overrides `$enabled` on save

Before, the `cache` entry won:

```php
$repo->save(new CacheControl(enabled: true, options: ['cache' => false]));
$repo->get()->enabled; // false
```

After, `$enabled` is always what gets written:

```php
$repo->save(new CacheControl(enabled: true, options: ['cache' => false]));
$repo->get()->enabled; // true
```

### Malformed entries are skipped on read

Given a hand-edited file:

```php
return ['pagecache' => ['routes' => ['/api.*' => ['cache' => false], '/flag.*' => false]]];
```

Before, `$repo->get()->routes` contained both entries. After, it contains only
`'/api.*' => ['cache' => false]`. Option entries with integer keys are likewise
skipped.

Projects that must stay on PHP 8.1 or 8.2 can keep using `^0.1`, which is
maintained on the `0.x` branch.

## Package renamed

2.0 is published as `contenir/contenir-page-cache`, with the namespace
`Contenir\PageCache\`. It declares `conflict` with `contenir/cache` and
`contenir/contenir-cache`, so old and new can never be installed together.
See [UPGRADE-page-cache.md](UPGRADE-page-cache.md) for the Composer and
import changes.
