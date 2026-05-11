<?php

declare(strict_types=1);

namespace Perfbase\Yii1\Tests\Unit;

use Perfbase\Yii1\PerfbaseComponent;
use Perfbase\Yii1\Support\PerfbaseClientProvider;
use Perfbase\Yii1\Support\PerfbaseErrorHandler;
use Perfbase\Yii1\Tests\Fixtures\TestPerfbaseComponent;
use Perfbase\Yii1\Tests\Fixtures\YiiState;
use PHPUnit\Framework\TestCase;

class PerfbaseComponentTest extends TestCase
{
    protected function tearDown(): void
    {
        YiiState::resetApplication();
        parent::tearDown();
    }

    public function test_configuration_accepts_custom_values(): void
    {
        $component = new PerfbaseComponent();
        $component->enabled = true;
        $component->debug = true;
        $component->api_key = 'test-key';
        $component->sample_rate = 1.0;
        $component->include = [
            'http' => ['site/*'],
            'console' => ['cache/*'],
            'cron' => ['schedule/*'],
        ];

        $config = $component->getConfig();

        self::assertTrue($config['enabled']);
        self::assertTrue($config['debug']);
        self::assertSame('test-key', $config['api_key']);
        self::assertSame(['schedule/*'], $config['include']['cron']);
    }

    public function test_configuration_exposes_http_status_allowlist(): void
    {
        $component = new PerfbaseComponent();
        $defaultConfig = $component->getConfig();

        self::assertSame([...range(200, 299), ...range(500, 599)], $defaultConfig['profile_http_status_codes']);

        $component->profile_http_status_codes = [200, 404];
        $customConfig = $component->getConfig();

        self::assertSame([200, 404], $customConfig['profile_http_status_codes']);
    }

    public function test_client_provider_and_error_handler_are_cached(): void
    {
        $component = new TestPerfbaseComponent();
        $component->enabled = true;
        $component->api_key = 'test-key';

        $firstProvider = $component->getClientProvider();
        $secondProvider = $component->getClientProvider();
        $firstErrorHandler = $component->getErrorHandler();
        $secondErrorHandler = $component->getErrorHandler();

        self::assertInstanceOf(PerfbaseClientProvider::class, $firstProvider);
        self::assertInstanceOf(PerfbaseErrorHandler::class, $firstErrorHandler);
        self::assertSame($firstProvider, $secondProvider);
        self::assertSame($firstErrorHandler, $secondErrorHandler);
    }

    public function test_preloaded_component_preserves_enabled_flag_from_yii_component_config(): void
    {
        $app = new \CWebApplication([
            'basePath' => dirname(__DIR__, 2),
            'preload' => ['perfbase'],
            'components' => [
                'perfbase' => [
                    'class' => TestPerfbaseComponent::class,
                    'enabled' => true,
                    'api_key' => 'test-key',
                    'sample_rate' => 1.0,
                ],
            ],
        ]);

        /** @var PerfbaseComponent $component */
        $component = $app->getComponent('perfbase');

        self::assertTrue($component->getConfig()['enabled']);
    }

    public function test_environment_defaults_to_production(): void
    {
        $component = new PerfbaseComponent();

        self::assertSame('production', $component->getEnvironment());
    }

    public function test_raw_component_config_resolution_returns_empty_array_when_not_found(): void
    {
        $app = new \CWebApplication([
            'basePath' => dirname(__DIR__, 2),
            'components' => [],
        ]);
        $component = new PerfbaseComponent();

        $result = $this->invokePrivate($component, 'resolveRawComponentConfig', [$app]);

        self::assertSame([], $result);
    }

    /**
     * @param array<int, mixed> $arguments
     * @return mixed
     */
    private function invokePrivate(object $object, string $method, array $arguments = [])
    {
        $reflection = new \ReflectionMethod($object, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($object, $arguments);
    }
}
