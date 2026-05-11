<p align="center">
  <a href="https://perfbase.com">
    <img src="https://cdn.perfbase.com/img/logo-full.svg" alt="Perfbase" width="300">
  </a>
</p>

<h3 align="center">Perfbase for Yii 1.1</h3>
<p align="center">
  Yii 1.1 integration for <a href="https://perfbase.com">Perfbase</a>.
</p>

<p align="center">
  <a href="https://packagist.org/packages/perfbase/yii1"><img src="https://img.shields.io/packagist/v/perfbase/yii1" alt="Packagist Version"></a>
  <a href="https://github.com/perfbaseorg/yii1/blob/main/LICENSE.txt"><img src="https://img.shields.io/packagist/l/perfbase/yii1" alt="License"></a>
  <a href="https://github.com/perfbaseorg/yii1/actions/workflows/ci.yml"><img src="https://img.shields.io/github/actions/workflow/status/perfbaseorg/yii1/ci.yml?branch=main" alt="CI"></a>
  <img src="https://img.shields.io/badge/php-7.4%2B-blue" alt="PHP Version">
  <img src="https://img.shields.io/badge/yii-1.1.x-blue" alt="Yii Version">
</p>

This package is a thin adapter over [`perfbase/php-sdk`](https://packagist.org/packages/perfbase/php-sdk). It keeps the framework layer thin, delegates transport and extension access to the shared SDK, fails open in production, and keeps action naming low-cardinality.

## Scope

v1 supports:

- HTTP request profiling
- Console command profiling
- Cron profiling as a classified console sub-context

v1 does not support:

- Queue workers
- Custom buffering or retries
- Yii-specific profiler UI or debug panels

This package is positioned as a legacy-support adapter for real Yii 1.1 applications.

## Requirements

- PHP `>=7.4 <8.5`
- Yii `1.1.x`
- `perfbase/php-sdk` `^1.0`
- The native Perfbase PHP extension available to the target PHP runtime

## Installation

```bash
composer require perfbase/yii1
```

Register the component as a preloaded application component in both your web and console configs:

```php
return [
    'preload' => ['perfbase'],
    'components' => [
        'perfbase' => [
            'class' => \Perfbase\Yii1\PerfbaseComponent::class,
            'enabled' => true,
            'api_key' => getenv('PERFBASE_API_KEY') ?: '',
            'sample_rate' => 0.1,
            'app_version' => '1.0.0',
        ],
    ],
];
```

Composer-based installation is required. Non-Composer Yii 1.1 applications are out of scope.

## Configuration

The adapter exposes this config contract:

```php
[
    'enabled' => false,
    'debug' => false,
    'log_errors' => true,
    'api_key' => '',
    'api_url' => 'https://ingress.perfbase.cloud',
    'sample_rate' => 0.1,
    'profile_http_status_codes' => [...range(200, 299), ...range(500, 599)],
    'timeout' => 10,
    'proxy' => null,
    'flags' => \Perfbase\SDK\FeatureFlags::DefaultFlags,
    'app_version' => '',
    'include' => [
        'http' => ['*'],
        'console' => ['*'],
        'cron' => [],
    ],
    'exclude' => [
        'http' => [],
        'console' => [],
        'cron' => [],
    ],
]
```

Config notes:

- `enabled` controls Perfbase profiling, not Yii component loading.
- `include.cron` is empty by default. That means console commands profile as `source=console` unless you explicitly classify them as cron.
- `sample_rate` must be numeric and between `0.0` and `1.0`.
- `profile_http_status_codes` defaults to `[...range(200, 299), ...range(500, 599)]`. Add codes such as `404` if you want to keep them, or set it to `[]` to disable HTTP trace submission entirely.
- `app_version` is application-defined.
- `environment` is derived from `YII_ENV` when available, otherwise `production`.

## HTTP Profiling

HTTP profiling is attached through Yii 1.1 application events:

- `onBeginRequest` starts the HTTP lifecycle
- `onException` and `onError` attach exception context
- `onEndRequest` finalizes and submits

Attributes include:

- `source=http`
- `action`
- `http_method`
- `http_url`
- `http_status_code`
- `user_ip`
- `user_agent`
- `user_id` when Yii user state is available and authenticated
- `hostname`
- `environment`
- `app_version`
- `php_version`

Behavior details:

- `action` prefers a stable Yii route
- `http_url` excludes query strings
- HTTP traces are only submitted when the response status code is in `profile_http_status_codes`
- query parameters are intentionally not included in the primary action/span fields
- response status defaults to `200` when Yii has not set a more specific status

## Console and Cron Profiling

Console profiling also uses application lifecycle hooks:

- `onBeginRequest` resolves the console command from `$_SERVER['argv']`
- `onException` and `onError` mark failures
- `onEndRequest` finalizes and submits

Console behavior:

- normal console commands use `source=console`
- commands matching `include.cron` and not matching `exclude.cron` use `source=cron`
- span names are `console.{command}` or `cron.{command}`
- exit code handling is best-effort and intentionally thin:
  - `0` on normal completion
  - `1` on uncaught exception or error paths

Cron is just a classification of console commands. There is no scheduler-specific integration in v1.

## Filters

Each context supports include/exclude filters:

- `http`
- `console`
- `cron`

Supported filter styles:

- `*`
- `.*`
- glob patterns such as `cache/*`
- regex patterns such as `/^GET \/users/`

Examples:

```php
'include' => [
    'http' => ['site/*', '/^GET \\/api\\//'],
    'console' => ['cache/*', 'migrate'],
    'cron' => ['schedule/*', 'jobs/run'],
],
'exclude' => [
    'http' => ['debug/*'],
    'console' => ['help'],
    'cron' => ['schedule/test'],
],
```

## Error Handling

The adapter is designed to fail open:

- if SDK construction fails, profiling becomes a no-op
- if the extension is unavailable, profiling becomes a no-op
- if submission fails, the application continues

Error mode behavior:

- `debug=false`: swallow errors and optionally log them
- `debug=true`: rethrow Perfbase adapter/runtime errors

## Runtime Architecture

The package stays intentionally thin:

- [`PerfbaseComponent.php`](/Users/ben/Projects/Perfbase/environment/projects/lib-yii1/src/PerfbaseComponent.php) owns config and lazy service creation
- [`PerfbaseBootstrap.php`](/Users/ben/Projects/Perfbase/environment/projects/lib-yii1/src/PerfbaseBootstrap.php) wires Yii application events
- lifecycle classes under [`src/Lifecycle`](/Users/ben/Projects/Perfbase/environment/projects/lib-yii1/src/Lifecycle) set context-specific attributes
- [`PerfbaseClientProvider.php`](/Users/ben/Projects/Perfbase/environment/projects/lib-yii1/src/Support/PerfbaseClientProvider.php) lazily builds the SDK client

The adapter does not implement a separate transport or persistence layer.

## Development

Install and verify with:

```bash
composer install
composer run test
composer run phpstan
```

## Documentation

Full documentation is available at [perfbase.com/docs](https://perfbase.com/docs).

- **Docs**: [perfbase.com/docs](https://perfbase.com/docs)
- **Issues**: [github.com/perfbaseorg/yii1/issues](https://github.com/perfbaseorg/yii1/issues)
- **Support**: [support@perfbase.com](mailto:support@perfbase.com)

## License

Apache-2.0. See [LICENSE.txt](LICENSE.txt).
