# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/).

## [2.0.0-RC1] - Unreleased

The first 2.0 pre-release, published as `contenir/contenir-page-cache`. The
API keeps its shape apart from the namespace, which moves from
`Contenir\Cache\` to `Contenir\PageCache\`. The major version also marks
the move to PHP 8.3+ and the QA toolchain shared by all Contenir 2.x
packages. See [UPGRADE-2.0.md](UPGRADE-2.0.md) and
[UPGRADE-page-cache.md](UPGRADE-page-cache.md).

The 2.0.0 and 2.1.0 tags published on 2026-10-05 as `contenir/contenir-cache`
were withdrawn and are folded into this release.

### Changed

- Renamed from `contenir/cache` (and the short-lived `contenir/contenir-cache`)
  to `contenir/contenir-page-cache`, and the namespace from `Contenir\Cache\`
  to `Contenir\PageCache\`. The package declares `conflict` (any version)
  with `contenir/cache` and `contenir/contenir-cache` rather than replacing
  them, because the namespace change means it cannot stand in for either.
  Composer refuses to install old and new together, so sites must move to
  the renamed adapter packages and contenir-cms releases at the same time.
  No `class_alias` shims are shipped.
- Requires PHP 8.3, 8.4 or 8.5. PHP 8.1 and 8.2 are no longer supported.
- `contenir/contenir-config` (`^2.1`) is now a required dependency instead of
  a suggestion. `Repository\FileRepository` cannot work without it.
- `LICENSE` names Contenir as the copyright holder, in line with the other
  Contenir packages, and uses the standard MIT wording.

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
  latest dependencies, with coverage reported to Codecov and Infection
  mutation testing at MSI 100%.
- Separate unit (no I/O) and integration (real filesystem) test suites, with
  100% line and branch coverage.

### Removed

- `squizlabs/php_codesniffer` and `phpcs.xml`, replaced by Mago via
  contenir-qa-tools.
- The `../config` path repository from `composer.json`.

## [0.1.1]

- Add LICENSE and README.

## [0.1.0]

- Initial release: `CacheControl`, `CacheControlRepositoryInterface`,
  `Repository\FileRepository` and `Repository\InMemoryRepository`.
