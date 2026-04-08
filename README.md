# Perfbase for Yii 1.1

`perfbase/yii1` is the legacy Yii 1.1 adapter for Perfbase.

It follows the Perfbase framework adapter guide: keep the framework layer thin, delegate transport and extension access to `perfbase/php-sdk`, fail open in production, and keep action naming low-cardinality.

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
    'api_url' => 'https://receiver.perfbase.com',
    'sample_rate' => 0.1,
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

## Local Development

During local development this package uses the sibling SDK checkout rather than Packagist:

```json
{
  "repositories": [
    {
      "type": "path",
      "url": "../lib-php-sdk"
    }
  ]
}
```

Install and verify with:

```bash
composer install
composer run test
composer run phpstan
```

## Support Statement

This is a legacy adapter.

- declared compatibility target: Yii `1.1.x`
- tested locally against current Composer-installable Yii 1.1
- broader `1.1.x` compatibility is best-effort

The main value of this package is giving legacy Yii 1.1 applications a first-party Perfbase path without requiring invasive framework rewrites.
