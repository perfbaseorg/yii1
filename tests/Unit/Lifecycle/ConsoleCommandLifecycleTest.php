<?php

declare(strict_types=1);

namespace Perfbase\Yii1\Tests\Unit\Lifecycle;

use Perfbase\Yii1\Lifecycle\ConsoleCommandLifecycle;
use Perfbase\Yii1\PerfbaseComponent;
use Perfbase\Yii1\Tests\Fixtures\RecordingPerfbaseClient;
use Perfbase\Yii1\Tests\Fixtures\TestPerfbaseClientProvider;
use Perfbase\Yii1\Tests\Fixtures\TestPerfbaseComponent;
use Perfbase\Yii1\Tests\Fixtures\YiiState;
use PHPUnit\Framework\TestCase;

class ConsoleCommandLifecycleTest extends TestCase
{
    protected function tearDown(): void
    {
        TestPerfbaseClientProvider::$client = null;
        YiiState::resetApplication();
        parent::tearDown();
    }

    public function test_console_command_profiles_and_sets_exit_code(): void
    {
        $client = new RecordingPerfbaseClient();
        TestPerfbaseClientProvider::$client = $client;

        $component = $this->createComponent();
        $lifecycle = new ConsoleCommandLifecycle('cache/flush', $component);
        $lifecycle->startProfiling();
        $lifecycle->setExitCode(2);
        $lifecycle->setException(new \RuntimeException('failed'));
        $lifecycle->stopProfiling();

        self::assertSame(['console.cache/flush'], $client->startedSpans);
        self::assertSame('console', $client->attributes['source']);
        self::assertSame('cache/flush', $client->attributes['action']);
        self::assertSame('2', $client->attributes['exit_code']);
        self::assertSame('failed', $client->attributes['exception']);
        self::assertSame(1, $client->submitCalls);
    }

    public function test_excluded_console_command_is_not_profiled(): void
    {
        $client = new RecordingPerfbaseClient();
        TestPerfbaseClientProvider::$client = $client;

        $component = $this->createComponent([
            'exclude' => ['http' => [], 'console' => ['cache/*'], 'cron' => []],
        ]);
        $lifecycle = new ConsoleCommandLifecycle('cache/flush', $component);
        $lifecycle->startProfiling();
        $lifecycle->stopProfiling();

        self::assertSame([], $client->startedSpans);
        self::assertSame(0, $client->submitCalls);
    }

    public function test_disabled_console_profiling_is_not_started(): void
    {
        $client = new RecordingPerfbaseClient();
        TestPerfbaseClientProvider::$client = $client;

        $component = $this->createComponent(['enabled' => false]);
        $lifecycle = new ConsoleCommandLifecycle('cache/flush', $component);
        $lifecycle->startProfiling();

        self::assertSame([], $client->startedSpans);
    }

    public function test_unknown_console_command_uses_unknown_fallback(): void
    {
        $client = new RecordingPerfbaseClient();
        TestPerfbaseClientProvider::$client = $client;

        $component = $this->createComponent();
        $lifecycle = new ConsoleCommandLifecycle('/', $component);
        $lifecycle->startProfiling();
        $lifecycle->stopProfiling();

        self::assertSame(['console.unknown'], $client->startedSpans);
        self::assertSame('unknown', $client->attributes['action']);
    }

    public function test_normalize_filters_returns_empty_array_for_non_array_values(): void
    {
        $lifecycle = new ConsoleCommandLifecycle('cache/flush', $this->createComponent());

        self::assertSame([], $this->invokePrivate($lifecycle, 'normalizeFilters', ['invalid']));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function createComponent(array $config = []): PerfbaseComponent
    {
        $component = new TestPerfbaseComponent();
        foreach ($config as $key => $value) {
            $component->$key = $value;
        }

        $component->enabled = $config['enabled'] ?? true;
        $component->sample_rate = $config['sample_rate'] ?? 1.0;
        $component->api_key = $config['api_key'] ?? 'test-key';
        $component->app_version = $config['app_version'] ?? '1.2.3';

        return $component;
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
