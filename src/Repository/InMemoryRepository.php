<?php

declare(strict_types=1);

namespace Contenir\Cache\Repository;

use Contenir\Cache\CacheControl;
use Contenir\Cache\CacheControlRepositoryInterface;

/**
 * Test-friendly repository that holds state in memory. Shipped in src/ so
 * consumers' tests can require contenir/cache and use this directly without
 * depending on autoload-dev.
 */
final class InMemoryRepository implements CacheControlRepositoryInterface
{
    private CacheControl $state;

    public function __construct(?CacheControl $initial = null)
    {
        $this->state = $initial ?? CacheControl::disabled();
    }

    public function get(): CacheControl
    {
        return $this->state;
    }

    public function save(CacheControl $state): void
    {
        $this->state = $state;
    }
}
