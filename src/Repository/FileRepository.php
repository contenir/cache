<?php

declare(strict_types=1);

namespace Contenir\Cache\Repository;

use Contenir\Cache\CacheControl;
use Contenir\Cache\CacheControlRepositoryInterface;
use Contenir\Config\Reader\PhpArray as ConfigReader;
use Contenir\Config\Writer\PhpArray as ConfigWriter;
use Override;

use function array_diff_key;
use function array_filter;
use function array_keys;
use function array_map;
use function array_replace;
use function is_array;
use function is_scalar;
use function is_string;

/**
 * PHP-array file backing store.
 *
 * The file follows the Laminas/Mezzio config-namespacing convention. The
 * Site's CacheStrategy listener reads the same `pagecache` namespace from
 * the merged config, so admin's writes and the listener's reads share one
 * shape:
 *
 *     return [
 *         'pagecache' => [
 *             'options' => [
 *                 'cache'              => true,    // master enable
 *                 'cache_with_query'   => true,
 *                 'cache_with_session' => true,
 *                 'make_id_with_query' => true,
 *                 // … other options.*
 *             ],
 *             'routes' => [
 *                 '/api.*'      => ['cache' => false],
 *                 '/dashboard.*'=> ['cache' => false],
 *             ],
 *         ],
 *     ];
 *
 * The repository owns:
 * - `pagecache.options.cache` (the master enable boolean)
 * - any keys present in `CacheControl::$options` (other pagecache.options.* keys)
 * - `pagecache.routes`
 *
 * On save:
 * - The master `cache` key is always written (true/false from CacheControl::$enabled).
 *   A `cache` entry in `$control->options` is ignored, so it can never
 *   contradict `$enabled`.
 * - Each option in `$control->options` is written under `pagecache.options.{key}`.
 *   Any sibling option in the file that's NOT in `$control->options` is left
 *   alone (operator-edited keys survive).
 * - `pagecache.routes` is REPLACED by `$control->routes`. Routes are
 *   conceptually a single admin-managed list; partial-replace would be
 *   confusing.
 * - All other top-level config keys (errors, maintenance, etc.) are
 *   preserved.
 *
 * A missing or unreadable file resolves to disabled with empty options/routes
 * so first-run consumers never serve cached content before the admin has
 * explicitly opted in. On read, option entries without a string name and
 * route entries that are not a pattern => options-array pair are skipped.
 */
final class FileRepository implements CacheControlRepositoryInterface
{
    private const string NAMESPACE_KEY = 'pagecache';
    private const string OPTIONS_KEY   = 'options';
    private const string CACHE_KEY     = 'cache';
    private const string ROUTES_KEY    = 'routes';
    private const string WRITE_LABEL   = 'cache control';

    public function __construct(
        private readonly string $filePath,
    ) {}

    /**
     * @return array<array-key, mixed>
     */
    private static function arrayOrEmpty(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private static function isTruthy(mixed $value): bool
    {
        return is_scalar($value) && (bool) $value;
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return array<string, mixed>
     */
    private static function stringKeyed(array $values): array
    {
        $named = [];
        foreach (array_keys($values) as $key) {
            if (! is_string($key)) {
                continue;
            }

            $named[$key] = $values[$key] ?? null;
        }

        return $named;
    }

    #[Override]
    public function get(): CacheControl
    {
        $namespace = self::arrayOrEmpty(ConfigReader::fromFile($this->filePath)[self::NAMESPACE_KEY] ?? null);
        $options   = self::stringKeyed(self::arrayOrEmpty($namespace[self::OPTIONS_KEY] ?? null));
        $routes    = array_filter(
            self::stringKeyed(self::arrayOrEmpty($namespace[self::ROUTES_KEY] ?? null)),
            is_array(...),
        );

        $enabled = self::isTruthy($options[self::CACHE_KEY] ?? false);

        // Strip the master enable from the sibling options array so it
        // can't accidentally be double-toggled via $control->options.
        unset($options[self::CACHE_KEY]);

        return new CacheControl(
            $enabled,
            $options,
            array_map(self::stringKeyed(...), $routes),
        );
    }

    #[Override]
    public function save(CacheControl $state): void
    {
        $config    = ConfigReader::fromFile($this->filePath);
        $namespace = self::arrayOrEmpty($config[self::NAMESPACE_KEY] ?? null);

        // Merge admin-managed options keys, preserving any sibling option
        // keys (e.g. ttl, priority) the operator hand-authored that aren't
        // in the admin form.
        $namespace[self::OPTIONS_KEY] = array_replace(
            self::arrayOrEmpty($namespace[self::OPTIONS_KEY] ?? null),
            [self::CACHE_KEY => $state->enabled],
            array_diff_key($state->options, [self::CACHE_KEY => true]),
        );

        // Routes are a single admin-managed list — full replace.
        $namespace[self::ROUTES_KEY] = $state->routes;

        $config[self::NAMESPACE_KEY] = $namespace;

        ConfigWriter::toFile($this->filePath, $config, self::WRITE_LABEL);
    }
}
