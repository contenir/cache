# Upgrading to contenir/contenir-page-cache

The package is now `contenir/contenir-page-cache` and the namespace is
`Contenir\PageCache\`. The public API is otherwise unchanged. No
`class_alias` shims are provided, so imports must be updated.

The package declares `conflict` (any version) with `contenir/cache` and
`contenir/contenir-cache`. It does not replace them, because the namespace
change means it cannot stand in for either. Composer refuses to install
old and new together, so sites switch deliberately.

Move to the renamed adapter packages (contenir-cache-mezzio,
contenir-cache-laminas-mvc) and contenir-cms releases at the same time.
Adapters still built against `Contenir\Cache\` will not install alongside
this package.

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
as strings in the same way. To cover fully qualified class names in code,
strings and config across a project in one pass:

```bash
grep -rlF 'Contenir\Cache\' src config tests | xargs sed -i 's/Contenir\\Cache\\/Contenir\\PageCache\\/g'
```

Configuration files may write the separator doubled (`Contenir\\Cache\\`),
so search for that form too.
