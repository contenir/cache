# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/).

## [2.0.0] - Unreleased

The public API is unchanged. The major version marks the move to PHP 8.3+
and the php-db QA toolchain shared by all Contenir 2.x packages. See
[UPGRADE-2.0.md](UPGRADE-2.0.md).

### Changed

- Requires PHP 8.3, 8.4 or 8.5. PHP 8.1 and 8.2 are no longer supported.
- `contenir/config` (`^0.2 || ^2.0`) is now a required dependency instead of a
  suggestion. `Repository\FileRepository` cannot work without it.

### Fixed

- `Repository\FileRepository::save()` ignores a `cache` entry in
  `CacheControl::$options`. Previously it overwrote the master switch, so the
  file could disagree with `CacheControl::$enabled`.
- `Repository\FileRepository::get()` skips option entries without a string
  name and route entries that are not a `pattern => [options]` pair, so the
  returned `CacheControl` always matches its documented shape. Previously a
  hand-edited route such as `'/api.*' => false` was passed through.

### Added

- Continuous integration on PHP 8.3, 8.4 and 8.5 against lowest, locked and
  latest dependencies, with coverage reported to Codecov.
- Separate unit (no I/O) and integration (real filesystem) test suites, with
  100% line and branch coverage.

### Removed

- `squizlabs/php_codesniffer` and `phpcs.xml`, replaced by Mago via
  `php-db/phpdb-qa-tools`.
- The `../config` path repository from `composer.json`.

## [0.1.1]

- Add LICENSE and README.

## [0.1.0]

- Initial release: `CacheControl`, `CacheControlRepositoryInterface`,
  `Repository\FileRepository` and `Repository\InMemoryRepository`.
