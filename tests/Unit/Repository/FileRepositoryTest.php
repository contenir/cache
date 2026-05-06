<?php

declare(strict_types=1);

namespace Contenir\Cache\Tests\Unit\Repository;

use Contenir\Cache\CacheControl;
use Contenir\Cache\Repository\FileRepository;
use Contenir\Config\Exception\WriteException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
#[Group('cache')]
final class FileRepositoryTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/contenir-cache-' . uniqid('', true);
        mkdir($this->tmpDir, 0o777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmpDir)) {
            $this->purge($this->tmpDir);
        }
        parent::tearDown();
    }

    private function purge(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $item) {
            if (is_dir($item)) {
                $this->purge($item);
                @rmdir($item);
            } else {
                @unlink($item);
            }
        }
        @rmdir($dir);
    }

    private function path(string $name = 'pagecache.local.php'): string
    {
        return $this->tmpDir . '/' . $name;
    }

    private function writeConfig(array $config): void
    {
        file_put_contents(
            $this->path(),
            "<?php\n\nreturn " . var_export($config, true) . ";\n",
        );
    }

    public function testGetReturnsDisabledWithEmptyOptionsAndRoutesWhenFileMissing(): void
    {
        $control = (new FileRepository($this->path()))->get();

        self::assertFalse($control->enabled);
        self::assertSame([], $control->options);
        self::assertSame([], $control->routes);
    }

    public function testGetReturnsDisabledWhenFileContentsAreNotAnArray(): void
    {
        file_put_contents($this->path(), "<?php\n\nreturn 'not an array';\n");

        self::assertFalse((new FileRepository($this->path()))->get()->enabled);
    }

    public function testGetReturnsDisabledWhenNamespaceKeyMissing(): void
    {
        $this->writeConfig(['somethingelse' => ['stuff' => 'here']]);

        self::assertFalse((new FileRepository($this->path()))->get()->enabled);
    }

    public function testGetReturnsEnabledWhenCacheKeyIsTrue(): void
    {
        $this->writeConfig(['pagecache' => ['options' => ['cache' => true]]]);

        self::assertTrue((new FileRepository($this->path()))->get()->enabled);
    }

    public function testGetExposesSiblingOptionsAlongsideEnabledFlag(): void
    {
        $this->writeConfig([
            'pagecache' => [
                'options' => [
                    'cache'              => true,
                    'cache_with_query'   => true,
                    'cache_with_session' => false,
                    'make_id_with_query' => true,
                    'ttl'                => 600,
                ],
            ],
        ]);

        $control = (new FileRepository($this->path()))->get();

        self::assertTrue($control->enabled);
        self::assertSame([
            'cache_with_query'   => true,
            'cache_with_session' => false,
            'make_id_with_query' => true,
            'ttl'                => 600,
        ], $control->options);
    }

    public function testGetStripsMasterCacheKeyFromOptionsArray(): void
    {
        // The master enable lives on $control->enabled — it must NOT also
        // appear in $control->options or consumers might double-toggle.
        $this->writeConfig([
            'pagecache' => [
                'options' => ['cache' => true, 'ttl' => 60],
            ],
        ]);

        $control = (new FileRepository($this->path()))->get();

        self::assertArrayNotHasKey('cache', $control->options);
        self::assertSame(['ttl' => 60], $control->options);
    }

    public function testGetReadsRoutes(): void
    {
        $this->writeConfig([
            'pagecache' => [
                'options' => ['cache' => true],
                'routes'  => [
                    '/api.*'       => ['cache' => false],
                    '/dashboard.*' => ['cache' => false],
                ],
            ],
        ]);

        $control = (new FileRepository($this->path()))->get();

        self::assertSame([
            '/api.*'       => ['cache' => false],
            '/dashboard.*' => ['cache' => false],
        ], $control->routes);
    }

    public function testSaveAndGetRoundTrip(): void
    {
        $repo = new FileRepository($this->path());
        $repo->save(new CacheControl(
            true,
            ['cache_with_query' => true, 'cache_with_post' => false, 'ttl' => 3600],
            ['/api.*' => ['cache' => false]],
        ));

        $reloaded = (new FileRepository($this->path()))->get();
        self::assertTrue($reloaded->enabled);
        self::assertSame(
            ['cache_with_query' => true, 'cache_with_post' => false, 'ttl' => 3600],
            $reloaded->options,
        );
        self::assertSame(['/api.*' => ['cache' => false]], $reloaded->routes);
    }

    public function testSaveProducesNamespacedFileFormat(): void
    {
        $repo = new FileRepository($this->path());
        $repo->save(new CacheControl(
            true,
            ['cache_with_query' => true],
            ['/api.*' => ['cache' => false]],
        ));

        $loaded = include $this->path();

        self::assertSame([
            'pagecache' => [
                'options' => [
                    'cache'            => true,
                    'cache_with_query' => true,
                ],
                'routes' => [
                    '/api.*' => ['cache' => false],
                ],
            ],
        ], $loaded);
    }

    public function testSavePreservesUnmanagedTopLevelKeys(): void
    {
        $this->writeConfig([
            'errors'      => ['pages' => [404 => ['title' => 'Lost']]],
            'maintenance' => ['state' => ['active' => true]],
        ]);

        $repo = new FileRepository($this->path());
        $repo->save(CacheControl::enabled());

        $reloaded = include $this->path();
        self::assertSame(404, array_key_first($reloaded['errors']['pages']));
        self::assertTrue($reloaded['maintenance']['state']['active']);
        self::assertTrue($reloaded['pagecache']['options']['cache']);
    }

    public function testSavePreservesOperatorOptionsNotInControl(): void
    {
        // Operator hand-wrote ttl + priority in pagecache.local.php. Admin
        // saves without those keys in CacheControl::$options. They must
        // survive (admin doesn't touch what it doesn't manage).
        $this->writeConfig([
            'pagecache' => [
                'options' => [
                    'ttl'      => 7200,
                    'priority' => 5,
                    'cache'    => false,
                ],
            ],
        ]);

        $repo = new FileRepository($this->path());
        $repo->save(new CacheControl(
            true,
            ['cache_with_query' => true],
        ));

        $reloaded = include $this->path();
        self::assertTrue($reloaded['pagecache']['options']['cache']);
        self::assertTrue($reloaded['pagecache']['options']['cache_with_query']);
        self::assertSame(7200, $reloaded['pagecache']['options']['ttl']);
        self::assertSame(5, $reloaded['pagecache']['options']['priority']);
    }

    public function testSaveReplacesRoutesEntirely(): void
    {
        // Routes are conceptually a single admin-managed list — partial
        // replace would be confusing. The new save's routes win.
        $this->writeConfig([
            'pagecache' => [
                'options' => ['cache' => true],
                'routes'  => [
                    '/old1.*' => ['cache' => false],
                    '/old2.*' => ['cache' => false],
                ],
            ],
        ]);

        $repo = new FileRepository($this->path());
        $repo->save(new CacheControl(true, [], ['/new.*' => ['cache' => false]]));

        $reloaded = include $this->path();
        self::assertSame(
            ['/new.*' => ['cache' => false]],
            $reloaded['pagecache']['routes'],
        );
    }

    public function testSaveWithEmptyRoutesClearsExistingRoutes(): void
    {
        $this->writeConfig([
            'pagecache' => [
                'options' => ['cache' => true],
                'routes'  => ['/old.*' => ['cache' => false]],
            ],
        ]);

        $repo = new FileRepository($this->path());
        $repo->save(CacheControl::enabled());

        $reloaded = include $this->path();
        self::assertSame([], $reloaded['pagecache']['routes']);
    }

    public function testSaveCreatesParentDirectoryIfMissing(): void
    {
        $nested = $this->tmpDir . '/nested/inner/pagecache.local.php';
        $repo   = new FileRepository($nested);

        $repo->save(CacheControl::enabled());

        self::assertFileExists($nested);
    }

    public function testSaveIsAtomicViaTempFileRename(): void
    {
        $repo = new FileRepository($this->path());
        $repo->save(CacheControl::enabled());

        self::assertFileDoesNotExist($this->path() . '.tmp');
    }

    public function testSaveThrowsWhenDestinationDirectoryUnwritable(): void
    {
        if (\function_exists('posix_geteuid') && posix_geteuid() === 0) {
            self::markTestSkipped('Running as root bypasses filesystem permission checks.');
        }

        $readOnly = $this->tmpDir . '/locked';
        mkdir($readOnly, 0o555, true);

        $repo = new FileRepository($readOnly . '/pagecache.local.php');

        try {
            $this->expectException(WriteException::class);
            $repo->save(CacheControl::enabled());
        } finally {
            chmod($readOnly, 0o755);
            rmdir($readOnly);
        }
    }
}
