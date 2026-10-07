# Upgrading to contenir/contenir-page-cache

The package is now `contenir/contenir-page-cache` and the namespace is
`Contenir\PageCache\`. The public API is otherwise unchanged. No
`class_alias` shims are provided, so imports must be updated.

The package declares `replace` for `contenir/cache` and
`contenir/contenir-cache`, so none of them can be installed together.

## Composer

```bash
composer remove contenir/contenir-cache && composer require contenir/contenir-page-cache
```

If you still require `contenir/cache`, remove that instead.

## Imports

```diff
-use Contenir\Cache\CacheControl;
-use Contenir\Cache\CacheControlRepositoryInterface;
-use Contenir\Cache\Repository\FileRepository;
-use Contenir\Cache\Repository\InMemoryRepository;
+use Contenir\PageCache\CacheControl;
+use Contenir\PageCache\CacheControlRepositoryInterface;
+use Contenir\PageCache\Repository\FileRepository;
+use Contenir\PageCache\Repository\InMemoryRepository;
```

Update any configuration or container definitions that name these classes
as strings in the same way.
