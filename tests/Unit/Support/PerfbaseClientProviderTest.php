<?php

declare(strict_types=1);

namespace Perfbase\Yii1\Tests\Unit\Support;

use Perfbase\SDK\Config;
use Perfbase\Yii1\Support\PerfbaseClientProvider;
use Perfbase\Yii1\Support\PerfbaseErrorHandler;
use Perfbase\Yii1\Tests\Fixtures\RecordingPerfbaseClient;
use PHPUnit\Framework\TestCase;

class PerfbaseClientProviderTest extends TestCase
{
    public function test_valid_config_returns_client(): void
    {
        $provider = new PerfbaseClientProvider(
            [
                'api_key' => 'test-key',
                'api_url' => 'https://receiver.perfbase.local',
                'flags' => 0,
                'timeout' => 10,
            ],
            new PerfbaseErrorHandler(false, false),
            static function (Config $config): RecordingPerfbaseClient {
                return new RecordingPerfbaseClient();
            }
        );

        self::assertInstanceOf(RecordingPerfbaseClient::class, $provider->getClient());
    }

    public function test_invalid_config_degrades_to_null(): void
    {
        $provider = new PerfbaseClientProvider(
            [
                'api_key' => '',
                'api_url' => 'https://receiver.perfbase.local',
            ],
            new PerfbaseErrorHandler(false, false)
        );

        self::assertNull($provider->getClient());
    }

    public function test_factory_must_return_perfbase_instance(): void
    {
        $provider = new PerfbaseClientProvider(
            [
                'api_key' => 'test-key',
                'api_url' => 'https://receiver.perfbase.local',
            ],
            new PerfbaseErrorHandler(false, false),
            static function () {
                return new \stdClass();
            }
        );

        self::assertNull($provider->getClient());
    }

    public function test_client_is_cached_and_empty_proxy_is_normalized_to_null(): void
    {
        $factoryCalls = 0;
        $provider = new PerfbaseClientProvider(
            [
                'api_key' => 'test-key',
                'api_url' => 'https://receiver.perfbase.local',
                'proxy' => '',
            ],
            new PerfbaseErrorHandler(false, false),
            static function (Config $config) use (&$factoryCalls): RecordingPerfbaseClient {
                $factoryCalls++;
                TestCase::assertNull($config->proxy);

                return new RecordingPerfbaseClient();
            }
        );

        $first = $provider->getClient();
        $second = $provider->getClient();

        self::assertInstanceOf(RecordingPerfbaseClient::class, $first);
        self::assertSame($first, $second);
        self::assertSame(1, $factoryCalls);
    }

    public function test_non_empty_proxy_is_passed_through_to_sdk_config(): void
    {
        $provider = new PerfbaseClientProvider(
            [
                'api_key' => 'test-key',
                'api_url' => 'https://receiver.perfbase.local',
                'proxy' => 'http://proxy.local:8080',
            ],
            new PerfbaseErrorHandler(false, false),
            static function (Config $config): RecordingPerfbaseClient {
                TestCase::assertSame('http://proxy.local:8080', $config->proxy);

                return new RecordingPerfbaseClient();
            }
        );

        self::assertInstanceOf(RecordingPerfbaseClient::class, $provider->getClient());
    }

    public function test_default_factory_returns_sdk_client(): void
    {
        $provider = new PerfbaseClientProvider(
            [
                'api_key' => 'test-key',
                'api_url' => 'https://receiver.perfbase.local',
            ],
            new PerfbaseErrorHandler(false, false)
        );

        self::assertInstanceOf(\Perfbase\SDK\Perfbase::class, $provider->getClient());
    }
}
