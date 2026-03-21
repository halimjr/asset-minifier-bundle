# Asset Minifier Bundle

A companion bundle for [Symfony Asset Mapper](https://symfony.com/doc/current/frontend/asset_mapper.html) that automatically minifies your JavaScript using [esbuild](https://esbuild.github.io/) — with no Node.js, no npm, and no build pipeline to configure.

Asset Mapper already handles import maps, cache-busting hashes, and ES module loading. This bundle completes the picture by shrinking your JS before it ships.

## Why this bundle?

Symfony Asset Mapper is deliberately free of Node.js tooling. But without a minification step, your JavaScript ships as readable source code — variable names, comments, and whitespace included. This bundle fills that gap by running each JS file through esbuild at compile time, using a self-contained binary that PHP downloads and manages for you.

No `package.json`. No `node_modules`. No extra tooling to maintain.

## Requirements

- PHP 8.1+
- `ext-phar` and `ext-zlib` (enabled by default in standard PHP builds)
- Symfony 6.3 or 7.x with `symfony/asset-mapper`

## Installation

```bash
composer require halimjr/asset-minifier-bundle
```

Register the bundle in `config/bundles.php`:

```php
return [
    // ...
    HalimJr\AssetMinifierBundle\AssetMinifierBundle::class => ['all' => true],
];
```

That's the entire setup. On the next `php bin/console asset-map:compile`, the esbuild binary is downloaded automatically and all your JavaScript assets are minified.

## Benchmarks

Measured on a real Symfony application with 45 Stimulus controllers:

| File | Original | Minified | Reduction |
|---|---|---|---|
| `main_controller.js` | 66.8 KB | 38.7 KB | **42%** |
| `list_controller.js` | 27.1 KB | 16.2 KB | **40%** |
| `detail_controller.js` | 20.1 KB | 11.8 KB | **41%** |
| `sync_controller.js` | 16.2 KB | 9.4 KB | **42%** |
| `alert_controller.js` | 14.3 KB | 8.5 KB | **41%** |
| **Total (45 controllers)** | **419 KB** | **308 KB** | **~42%** |

Minification includes whitespace removal, comment stripping, and identifier mangling (local variable names shortened to single characters).

## How it works

The bundle registers a compiler with Symfony Asset Mapper's pipeline via `AssetCompilerInterface`. During compilation, each of your JavaScript files is piped through esbuild with `--minify --format=esm`, which performs:

- **Whitespace and comment removal**
- **Identifier mangling** — local variable names are shortened to single characters
- **Dead-code elimination** — unreachable branches are removed

The esbuild binary (~5 MB compressed) is downloaded once from the npm registry and cached in `var/esbuild/`. It is version-pinned by the bundle and re-downloaded automatically when you upgrade to a bundle version that ships a newer esbuild release.

**Vendor assets are intentionally skipped.** Pre-minified packages managed via importmap (e.g. `@hotwired/stimulus`, `apexcharts`) pass through untouched.

**Stimulus controllers are safe.** The bundle runs after Symfony's `JavaScriptImportPathCompiler`, so import paths are resolved and registered in the importmap before minification occurs. Method names used in `data-action` attributes are not mangled.

## Configuration

No configuration is required for standard use. The following options are available if you need them:

```yaml
# config/packages/asset_minifier.yaml
asset_minifier:
    esbuild_version: '0.25.2'  # pin to a specific esbuild version
    binary_path: null           # use a pre-existing binary instead of downloading
    source_maps: false          # set to true to embed inline source maps for production debugging
```

### Source maps

When `source_maps: true`, an inline source map is appended to each minified file. This lets you see original variable names and line numbers in browser DevTools, which is useful for debugging production issues without deploying unminified code.

Note that inline source maps increase file size. For most projects the trade-off is worth it in staging environments; in production, leave it disabled (the default).

`binary_path` is useful in environments where downloading from the npm registry is restricted, or when you manage the esbuild binary through your own provisioning (e.g. system packages, CI cache layers, Docker images).

## Commands

| Command | Description |
|---|---|
| `asset-minifier:download` | Downloads the esbuild binary for the current platform |
| `asset-minifier:download --dry-run` | Previews what would be downloaded without downloading |

## Deployment

### GitHub Actions

```yaml
- name: Install PHP dependencies
  run: composer install --no-dev --optimize-autoloader

- name: Download esbuild
  run: php bin/console asset-minifier:download

- name: Compile assets
  run: php bin/console asset-map:compile --env=prod
```

### GitLab CI

```yaml
deploy:
  script:
    - composer install --no-dev --optimize-autoloader
    - php bin/console asset-minifier:download
    - php bin/console asset-map:compile --env=prod
    - php bin/console cache:clear --env=prod
```

### General deploy script

```bash
composer install --no-dev --optimize-autoloader
php bin/console asset-minifier:download
php bin/console asset-map:compile --env=prod
php bin/console cache:clear --env=prod
```

The download command is idempotent — it does nothing if the correct binary version is already present. You can safely run it on every deploy.

Add the binary directory to `.gitignore`:

```
/var/esbuild/
```

## Supported platforms

| OS | x86\_64 | arm64 |
|---|---|---|
| Linux | ✓ | ✓ |
| macOS | ✓ | ✓ |
| Windows | ✓ | ✓ |

## Contributing

Bug reports and pull requests are welcome at [github.com/halimjr/asset-minifier-bundle](https://github.com/halimjr/asset-minifier-bundle).

## License

MIT — see [LICENSE](LICENSE).
