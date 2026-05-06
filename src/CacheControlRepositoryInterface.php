<?php

declare(strict_types=1);

namespace Contenir\Cache;

use RuntimeException;

/**
 * Persists and retrieves the current CacheControl state.
 *
 * Implementations should treat a missing/unreadable backing store as a
 * never-purged state rather than throwing — first-run consumers must not crash
 * before the admin has ever clicked "purge". Save errors throw so the admin UI
 * can surface them.
 */
interface CacheControlRepositoryInterface
{
    public function get(): CacheControl;

    /**
     * @throws RuntimeException If the state cannot be persisted.
     */
    public function save(CacheControl $state): void;
}
