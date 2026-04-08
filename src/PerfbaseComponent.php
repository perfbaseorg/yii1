<?php

declare(strict_types=1);

namespace Perfbase\Yii1;

use Perfbase\SDK\FeatureFlags;
use Perfbase\Yii1\Support\PerfbaseClientProvider;
use Perfbase\Yii1\Support\PerfbaseErrorHandler;

class PerfbaseComponent extends \CApplicationComponent
{
    public bool $enabled = false;
    public bool $debug = false;
    public bool $log_errors = true;
    public string $api_key = '';
    public string $api_url = 'https://ingress.perfbase.cloud';
    /** @var float|int|string */
    public $sample_rate = 0.1;
    public int $timeout = 10;
    public ?string $proxy = null;
    public int $flags = FeatureFlags::DefaultFlags;
    public string $app_version = '';

    /** @var array<string, array<int, string>> */
    public array $include = [
        'http' => ['*'],
        'console' => ['*'],
        'cron' => [],
    ];

    /** @var array<string, array<int, string>> */
    public array $exclude = [
        'http' => [],
        'console' => [],
        'cron' => [],
    ];

    private ?PerfbaseClientProvider $clientProvider = null;
    private ?PerfbaseErrorHandler $errorHandler = null;
    private ?PerfbaseBootstrap $bootstrapper = null;

    public function init(): void
    {
        parent::init();

        $app = \Yii::app();
        $this->hydrateEnabledFlag($app);
        $this->bootstrap($app);
    }

    public function bootstrap(\CApplication $app): void
    {
        $this->getBootstrapper()->bootstrap($app);
    }

    public function getClientProvider(): PerfbaseClientProvider
    {
        if ($this->clientProvider === null) {
            $this->clientProvider = $this->createClientProvider();
        }

        return $this->clientProvider;
    }

    public function getErrorHandler(): PerfbaseErrorHandler
    {
        if ($this->errorHandler === null) {
            $this->errorHandler = $this->createErrorHandler();
        }

        return $this->errorHandler;
    }

    public function getEnvironment(): string
    {
        if (defined('YII_ENV')) {
            return (string) YII_ENV;
        }

        return 'production';
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        return [
            'enabled' => $this->enabled,
            'debug' => $this->debug,
            'log_errors' => $this->log_errors,
            'api_key' => $this->api_key,
            'api_url' => $this->api_url,
            'sample_rate' => $this->sample_rate,
            'timeout' => $this->timeout,
            'proxy' => $this->proxy,
            'flags' => $this->flags,
            'app_version' => $this->app_version,
            'include' => [
                'http' => $this->normalizeFilters($this->include['http'] ?? ['*']),
                'console' => $this->normalizeFilters($this->include['console'] ?? ['*']),
                'cron' => $this->normalizeFilters($this->include['cron'] ?? []),
            ],
            'exclude' => [
                'http' => $this->normalizeFilters($this->exclude['http'] ?? []),
                'console' => $this->normalizeFilters($this->exclude['console'] ?? []),
                'cron' => $this->normalizeFilters($this->exclude['cron'] ?? []),
            ],
        ];
    }

    protected function createClientProvider(): PerfbaseClientProvider
    {
        return new PerfbaseClientProvider($this->getConfig(), $this->getErrorHandler());
    }

    protected function createErrorHandler(): PerfbaseErrorHandler
    {
        return new PerfbaseErrorHandler($this->debug, $this->log_errors);
    }

    private function getBootstrapper(): PerfbaseBootstrap
    {
        if ($this->bootstrapper === null) {
            $this->bootstrapper = new PerfbaseBootstrap($this);
        }

        return $this->bootstrapper;
    }

    /**
     * @param mixed $filters
     * @return array<int, string>
     */
    private function normalizeFilters($filters): array
    {
        if (!is_array($filters)) {
            return [];
        }

        return array_values(array_filter($filters, static function ($filter): bool {
            return is_string($filter) && $filter !== '';
        }));
    }

    private function hydrateEnabledFlag(\CApplication $app): void
    {
        $config = $this->resolveRawComponentConfig($app);
        if (array_key_exists('enabled', $config)) {
            $this->enabled = (bool) $config['enabled'];
        }
    }

    /**
     * Yii 1.1 reserves `enabled` in component config to control lazy loading,
     * so we have to read the original raw component config to preserve the
     * Perfbase config contract.
     *
     * @return array<string, mixed>
     */
    private function resolveRawComponentConfig(\CApplication $app): array
    {
        $moduleReflection = new \ReflectionClass(\CModule::class);
        $property = $moduleReflection->getProperty('_componentConfig');
        $property->setAccessible(true);
        $configs = $property->getValue($app);

        if (!is_array($configs)) {
            return [];
        }

        foreach ($configs as $config) {
            if (!is_array($config)) {
                continue;
            }

            $configuredClass = $config['class'] ?? null;
            if (!is_string($configuredClass) || !class_exists($configuredClass)) {
                continue;
            }

            if ($configuredClass === self::class || is_subclass_of($configuredClass, self::class)) {
                return $config;
            }
        }

        return [];
    }
}
