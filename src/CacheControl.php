<?php

declare(strict_types=1);

namespace Contenir\PageCache;

/**
 * Immutable cache-control state.
 *
 * `enabled` is the master switch admin toggles — maps directly to
 * `pagecache.options.cache` in the Laminas/Mezzio merged config and is the
 * single key the Site's CacheStrategy listener consults to decide whether
 * to even try caching.
 *
 * `options` carries the rest of `pagecache.options.*` (cache_with_*,
 * make_id_with_*, ttl, priority) — operator-level toggles that admin can
 * override per-Site.
 *
 * `routes` is the regex => options-overrides map (e.g.
 * `'/api.*' => ['cache' => false]`) that lets admin exclude or override
 * caching on specific URL patterns.
 *
 * The default state (no file written yet) is `disabled` with empty options
 * and routes. First-run consumers never serve cached content until admin
 * has explicitly opted in.
 */
final class CacheControl
{
    /**
     * @param array<string, mixed> $options Sibling keys under
     *     `pagecache.options.*`: `cache_with_query`, `cache_with_post`,
     *     `cache_with_session`, `cache_with_files`, `cache_with_cookie`,
     *     `make_id_with_query`, `make_id_with_post`, `make_id_with_session`,
     *     `make_id_with_files`, `make_id_with_cookie`, `ttl`, `priority`.
     *     The `cache` master switch is intentionally NOT in this array — it
     *     lives on `$enabled` so consumers don't accidentally double-toggle.
     * @param array<string, array<string, mixed>> $routes Regex pattern =>
     *     options-overrides (typically `['cache' => false]` to bypass
     *     specific URL families).
     */
    public function __construct(
        public readonly bool $enabled,
        public readonly array $options = [],
        public readonly array $routes = [],
    ) {}

    public static function disabled(): self
    {
        return new self(enabled: false);
    }

    public static function enabled(): self
    {
        return new self(enabled: true);
    }
}
