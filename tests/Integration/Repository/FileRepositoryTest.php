<?php

declare(strict_types=1);

namespace Contenir\Cache\Tests\Integration\Repository;

use Contenir\Cache\CacheControl;
use Contenir\Cache\Repository\FileRepository;
use Contenir\Cache\Tests\Trait\TemporaryDirectoryTrait;
use Contenir\Config\Exception\WriteException;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function mkdir;
use function sprintf;
use function var_export;

#[Group('integration')]
#[Group('cache')]
final class FileRepositoryTest extends TestCase
{
    use TemporaryDirectoryTrait;

    /**
     * @return array<string, array{string, CacheControl}>
     */
    public static function storedFileProvider(): array
    {
        return [
            'not an array'              => ["'not an array'", CacheControl::disabled()],
            'namespace missing'         => [
                self::export(['somethingelse' => ['stuff' => 'here']]),
                CacheControl::disabled(),
            ],
            'namespace not an array'    => [self::export(['pagecache' => 'on']), CacheControl::disabled()],
            'options not an array'      => [
                self::export(['pagecache' => ['options' => true]]),
                CacheControl::disabled(),
            ],
            'cache flag true'           => [
                self::export(['pagecache' => ['options' => ['cache' => true]]]),
                CacheControl::enabled(),
            ],
            'cache flag truthy integer' => [
                self::export(['pagecache' => ['options' => ['cache' => 1]]]),
                CacheControl::enabled(),
            ],
            'cache flag false'          => [
                self::export(['pagecache' => ['options' => ['cache' => false]]]),
                CacheControl::disabled(),
            ],
            'cache flag not a scalar'   => [
                self::export(['pagecache' => ['options' => ['cache' => ['yes']]]]),
                CacheControl::disabled(),
            ],
            'sibling options'           => [
                self::export([
                    'pagecache' => ['options' => [
                        'cache'              => true,
                        'cache_with_query'   => true,
                        'cache_with_session' => false,
                        'make_id_with_query' => true,
                        'ttl'                => 600,
                    ]],
                ]),
                new CacheControl(
                    enabled: true,
                    options: [
                        'cache_with_query'   => true,
                        'cache_with_session' => false,
                        'make_id_with_query' => true,
                        'ttl'                => 600,
                    ],
                ),
            ],
            'unnamed options skipped'   => [
                self::export(['pagecache' => ['options' => ['cache' => true, 0 => 'stray', 'ttl' => 60]]]),
                new CacheControl(
                    enabled: true,
                    options: ['ttl' => 60],
                ),
            ],
            'routes'                    => [
                self::export([
                    'pagecache' => [
                        'options' => ['cache' => true],
                        'routes'  => [
                            '/api.*'       => ['cache' => false],
                            '/dashboard.*' => ['cache' => false],
                        ],
                    ],
                ]),
                new CacheControl(
                    enabled: true,
                    routes: [
                        '/api.*'       => ['cache' => false],
                        '/dashboard.*' => ['cache' => false],
                    ],
                ),
            ],
            'routes not an array'       => [
                self::export(['pagecache' => ['options' => ['cache' => true], 'routes' => 'none']]),
                CacheControl::enabled(),
            ],
            'malformed routes skipped'  => [
                self::export([
                    'pagecache' => [
                        'routes' => [
                            '/api.*'  => ['cache' => false, 0 => 'stray'],
                            '/flag.*' => false,
                            0         => ['cache' => false],
                        ],
                    ],
                ]),
                new CacheControl(
                    enabled: false,
                    routes: ['/api.*' => ['cache' => false]],
                ),
            ],
        ];
    }

    private static function export(mixed $value): string
    {
        return var_export(
            value: $value,
            return: true,
        );
    }

    #[Test]
    public function clearsExistingRoutesWhenSavingNone(): void
    {
        $this->store(['pagecache' => ['options' => ['cache' => true], 'routes' => ['/old.*' => ['cache' => false]]]]);

        (new FileRepository($this->path()))->save(CacheControl::enabled());

        static::assertSame([], $this->stored()['pagecache']['routes']);
    }

    #[Test]
    public function createsMissingParentDirectories(): void
    {
        $nested = $this->path('nested/inner/pagecache.local.php');

        (new FileRepository($nested))->save(CacheControl::enabled());

        static::assertEquals(CacheControl::enabled(), (new FileRepository($nested))->get());
    }

    #[Test]
    public function ignoresACacheOptionThatContradictsTheEnabledFlag(): void
    {
        (new FileRepository($this->path()))->save(new CacheControl(
            enabled: true,
            options: ['cache' => false, 'ttl' => 60],
        ));

        static::assertTrue((new FileRepository($this->path()))->get()->enabled);
    }

    #[Test]
    public function preservesOperatorOptionsTheStateDoesNotManage(): void
    {
        $this->store(['pagecache' => ['options' => ['ttl' => 7200, 'priority' => 5, 'cache' => false]]]);

        (new FileRepository($this->path()))->save(new CacheControl(
            enabled: true,
            options: ['cache_with_query' => true],
        ));

        static::assertSame(
            ['ttl' => 7200, 'priority' => 5, 'cache' => true, 'cache_with_query' => true],
            $this->stored()['pagecache']['options'],
        );
    }

    #[Test]
    public function preservesUnmanagedTopLevelKeys(): void
    {
        $this->store([
            'errors'      => ['pages' => [404 => ['title' => 'Lost']]],
            'maintenance' => ['state' => ['active' => true]],
        ]);

        (new FileRepository($this->path()))->save(CacheControl::enabled());

        static::assertSame(
            [
                'errors'      => ['pages' => [404 => ['title' => 'Lost']]],
                'maintenance' => ['state' => ['active' => true]],
                'pagecache'   => ['options' => ['cache' => true], 'routes' => []],
            ],
            $this->stored(),
        );
    }

    #[Test]
    public function readsBackWhatItSaved(): void
    {
        $saved = new CacheControl(
            enabled: true,
            options: ['cache_with_query' => true, 'cache_with_post' => false, 'ttl' => 3600],
            routes: ['/api.*' => ['cache' => false]],
        );

        (new FileRepository($this->path()))->save($saved);

        static::assertEquals($saved, (new FileRepository($this->path()))->get());
    }

    #[Test]
    public function readsDisabledStateWhenTheFileIsMissing(): void
    {
        static::assertEquals(CacheControl::disabled(), (new FileRepository($this->path()))->get());
    }

    #[Test]
    #[DataProvider('storedFileProvider')]
    public function readsStateFromTheStoredFile(string $returned, CacheControl $expected): void
    {
        file_put_contents($this->path(), sprintf("<?php\n\nreturn %s;\n", $returned));

        static::assertEquals($expected, (new FileRepository($this->path()))->get());
    }

    #[Test]
    public function replacesExistingRoutesEntirely(): void
    {
        $this->store([
            'pagecache' => [
                'options' => ['cache' => true],
                'routes'  => ['/old1.*' => ['cache' => false], '/old2.*' => ['cache' => false]],
            ],
        ]);

        (new FileRepository($this->path()))->save(new CacheControl(
            enabled: true,
            routes: ['/new.*' => ['cache' => false]],
        ));

        static::assertSame(['/new.*' => ['cache' => false]], $this->stored()['pagecache']['routes']);
    }

    #[Test]
    public function replacesMalformedNamespaceAndOptionsEntries(): void
    {
        $this->store(['pagecache' => ['options' => 'broken'], 'other' => 1]);

        (new FileRepository($this->path()))->save(CacheControl::enabled());

        static::assertSame(
            ['pagecache' => ['options' => ['cache' => true], 'routes' => []], 'other' => 1],
            $this->stored(),
        );
    }

    #[Test]
    public function throwsALabelledWriteExceptionWhenTheDirectoryIsNotWritable(): void
    {
        $this->skipWhenRunningAsRoot();
        mkdir($this->path('locked'), permissions: 0o555);

        $this->expectException(WriteException::class);
        $this->expectExceptionMessage('cache control');

        (new FileRepository($this->path('locked/pagecache.local.php')))->save(CacheControl::enabled());
    }

    #[Test]
    public function writesTheNamespacedFileFormat(): void
    {
        (new FileRepository($this->path()))->save(new CacheControl(
            enabled: true,
            options: ['cache_with_query' => true],
            routes: ['/api.*' => ['cache' => false]],
        ));

        static::assertSame(
            [
                'pagecache' => [
                    'options' => [
                        'cache'            => true,
                        'cache_with_query' => true,
                    ],
                    'routes'  => [
                        '/api.*' => ['cache' => false],
                    ],
                ],
            ],
            $this->stored(),
        );
    }

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTemporaryDirectory();
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->tearDownTemporaryDirectory();
        parent::tearDown();
    }

    /**
     * @param array<array-key, mixed> $config
     */
    private function store(array $config): void
    {
        file_put_contents($this->path(), sprintf("<?php\n\nreturn %s;\n", self::export($config)));
    }

    /**
     * @return array<array-key, mixed>
     */
    private function stored(): array
    {
        /** @var array<array-key, mixed> */
        return include $this->path();
    }
}
