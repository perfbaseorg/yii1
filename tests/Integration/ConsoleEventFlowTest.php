<?php

declare(strict_types=1);

namespace Perfbase\Yii1\Tests\Integration;

use Perfbase\Yii1\Tests\Fixtures\RecordingPerfbaseClient;
use Perfbase\Yii1\Tests\Fixtures\TestPerfbaseClientProvider;
use Perfbase\Yii1\Tests\Fixtures\TestPerfbaseComponent;
use Perfbase\Yii1\Tests\Fixtures\YiiState;
use PHPUnit\Framework\TestCase;

class ConsoleEventFlowTest extends TestCase
{
    protected function tearDown(): void
    {
        TestPerfbaseClientProvider::$client = null;
        YiiState::resetApplication();
        unset($_SERVER['argv']);
        parent::tearDown();
    }

    public function test_command_start_terminate_submit_flow(): void
    {
        $client = new RecordingPerfbaseClient();
        TestPerfbaseClientProvider::$client = $client;
        $_SERVER['argv'] = ['yiic', 'cache', 'flush'];
        $app = $this->createApplication();

        $app->onBeginRequest(new \CEvent($app));
        $app->onEndRequest(new \CEvent($app));

        self::assertSame(['artisan'], $client->startedSpans);
        self::assertSame('cache/flush', $client->attributes['action']);
        self::assertSame('0', $client->attributes['exit_code']);
        self::assertSame(1, $client->submitCalls);
    }

    public function test_cron_classification_uses_cron_lifecycle(): void
    {
        $client = new RecordingPerfbaseClient();
        TestPerfbaseClientProvider::$client = $client;
        $_SERVER['argv'] = ['yiic', 'schedule', 'run'];
        $app = $this->createApplication([
            'include' => ['http' => ['*'], 'console' => ['*'], 'cron' => ['schedule/*']],
        ]);

        $app->onBeginRequest(new \CEvent($app));
        $app->onEndRequest(new \CEvent($app));

        self::assertSame(['cron'], $client->startedSpans);
        self::assertSame('cron', $client->attributes['source']);
    }

    public function test_command_failure_path_captures_exception_and_exit_code(): void
    {
        $client = new RecordingPerfbaseClient();
        TestPerfbaseClientProvider::$client = $client;
        $_SERVER['argv'] = ['yiic', 'cache', 'flush'];
        $app = $this->createApplication();

        $app->onBeginRequest(new \CEvent($app));
        $app->onException(new \CExceptionEvent($app, new \CException('command failed')));
        $app->onEndRequest(new \CEvent($app));

        self::assertSame('command failed', $client->attributes['exception']);
        self::assertSame('1', $client->attributes['exit_code']);
        self::assertSame(1, $client->submitCalls);
    }

    /**
     * @param array<string, mixed> $perfbaseConfig
     */
    private function createApplication(array $perfbaseConfig = []): \CConsoleApplication
    {
        return new \CConsoleApplication([
            'basePath' => dirname(__DIR__, 2),
            'preload' => ['perfbase'],
            'components' => [
                'perfbase' => array_merge([
                    'class' => TestPerfbaseComponent::class,
                    'enabled' => true,
                    'sample_rate' => 1.0,
                    'api_key' => 'test-key',
                    'app_version' => 'test-suite',
                    'include' => ['http' => ['*'], 'console' => ['*'], 'cron' => []],
                    'exclude' => ['http' => [], 'console' => [], 'cron' => []],
                ], $perfbaseConfig),
            ],
        ]);
    }
}
