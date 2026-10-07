<?php

declare(strict_types=1);

namespace Contenir\PageCache\Tests\Unit;

use Contenir\PageCache\CacheControl;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
#[Group('cache')]
final class CacheControlTest extends TestCase
{
    #[Test]
    public function disabledFactoryReturnsDisabledStateWithNoOptionsOrRoutes(): void
    {
        static::assertEquals(
            new CacheControl(
                enabled: false,
                options: [],
                routes: [],
            ),
            CacheControl::disabled(),
        );
    }

    #[Test]
    public function enabledFactoryReturnsEnabledStateWithNoOptionsOrRoutes(): void
    {
        static::assertEquals(
            new CacheControl(
                enabled: true,
                options: [],
                routes: [],
            ),
            CacheControl::enabled(),
        );
    }

    #[Test]
    public function exposesConstructorArgumentsAsProperties(): void
    {
        $control = new CacheControl(
            enabled: true,
            options: ['ttl' => 600],
            routes: ['/api.*' => ['cache' => false]],
        );

        static::assertSame(
            [true, ['ttl' => 600], ['/api.*' => ['cache' => false]]],
            [$control->enabled, $control->options, $control->routes],
        );
    }
}
