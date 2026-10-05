<?php

declare(strict_types=1);

namespace Contenir\Cache\Repository;

use Contenir\Cache\CacheControl;
use Contenir\Cache\CacheControlRepositoryInterface;
use Override;

/**
 * Test-friendly repository that holds state in memory. Shipped in src/ so
 * consumers' tests can require contenir/contenir-cache and use this directly
 * without depending on autoload-dev.
 */
final class InMemoryRepository implements CacheControlRepositoryInterface
{
    private CacheControl $state;

    public function __construct(?CacheControl $initial = null)
    {
        $this->state = $initial ?? CacheControl::disabled();
    }

    #[Override]
    public function get(): CacheControl
    {
        return $this->state;
    }

    #[Override]
    public function save(CacheControl $state): void
    {
        $this->state = $state;
    }
}
