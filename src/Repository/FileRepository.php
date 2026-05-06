<?php

declare(strict_types=1);

namespace Contenir\Cache\Repository;

use Contenir\Cache\CacheControl;
use Contenir\Cache\CacheControlRepositoryInterface;
use Contenir\Config\Reader\PhpArray as ConfigReader;
use Contenir\Config\Writer\PhpArray as ConfigWriter;

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
 * explicitly opted in.
 */
final class FileRepository implements CacheControlRepositoryInterface
{
    private const NAMESPACE_KEY = 'pagecache';
    private const OPTIONS_KEY   = 'options';
    private const CACHE_KEY     = 'cache';
    private const ROUTES_KEY    = 'routes';
    private const WRITE_LABEL   = 'cache control';

    public function __construct(
        private readonly string $filePath,
    ) {
    }

    public function get(): CacheControl
    {
        $config = ConfigReader::fromFile($this->filePath);

        $optionsData = $config[self::NAMESPACE_KEY][self::OPTIONS_KEY] ?? null;
        $routesData  = $config[self::NAMESPACE_KEY][self::ROUTES_KEY]  ?? null;

        $enabled = false;
        $options = [];

        if (is_array($optionsData)) {
            $enabled = (bool) ($optionsData[self::CACHE_KEY] ?? false);

            // Strip the master enable from the sibling options array so it
            // can't accidentally be double-toggled via $control->options.
            unset($optionsData[self::CACHE_KEY]);
            $options = $optionsData;
        }

        $routes = is_array($routesData) ? $routesData : [];

        return new CacheControl($enabled, $options, $routes);
    }

    public function save(CacheControl $state): void
    {
        $config = ConfigReader::fromFile($this->filePath);

        if (! isset($config[self::NAMESPACE_KEY]) || ! is_array($config[self::NAMESPACE_KEY])) {
            $config[self::NAMESPACE_KEY] = [];
        }
        if (
            ! isset($config[self::NAMESPACE_KEY][self::OPTIONS_KEY])
            || ! is_array($config[self::NAMESPACE_KEY][self::OPTIONS_KEY])
        ) {
            $config[self::NAMESPACE_KEY][self::OPTIONS_KEY] = [];
        }

        $config[self::NAMESPACE_KEY][self::OPTIONS_KEY][self::CACHE_KEY] = $state->enabled;

        // Merge admin-managed options keys, preserving any sibling option
        // keys (e.g. ttl, priority) the operator hand-authored that aren't
        // in the admin form.
        foreach ($state->options as $key => $value) {
            $config[self::NAMESPACE_KEY][self::OPTIONS_KEY][$key] = $value;
        }

        // Routes are a single admin-managed list — full replace.
        $config[self::NAMESPACE_KEY][self::ROUTES_KEY] = $state->routes;

        ConfigWriter::toFile($this->filePath, $config, self::WRITE_LABEL);
    }
}
