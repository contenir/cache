<?php

declare(strict_types=1);

namespace Contenir\PageCache\Tests\Unit\Repository;

use Contenir\PageCache\CacheControl;
use Contenir\PageCache\Repository\InMemoryRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
#[Group('cache')]
final class InMemoryRepositoryTest extends TestCase
{
    #[Test]
    public function returnsTheLastSavedState(): void
    {
        $repository = new InMemoryRepository(CacheControl::enabled());
        $saved      = new CacheControl(
            enabled: false,
            options: ['ttl' => 60],
        );

        $repository->save($saved);

        static::assertSame($saved, $repository->get());
    }

    #[Test]
    public function startsDisabledWhenNoInitialStateIsGiven(): void
    {
        static::assertEquals(CacheControl::disabled(), (new InMemoryRepository())->get());
    }

    #[Test]
    public function startsWithTheGivenInitialState(): void
    {
        $initial = CacheControl::enabled();

        static::assertSame($initial, (new InMemoryRepository($initial))->get());
    }
}
