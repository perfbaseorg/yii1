<?php

declare(strict_types=1);

namespace Perfbase\Yii1\Tests\Unit;

use Perfbase\Yii1\PerfbaseComponent;
use Perfbase\Yii1\Tests\Fixtures\RecordingPerfbaseClient;
use Perfbase\Yii1\Tests\Fixtures\ServerFallbackRequest;
use Perfbase\Yii1\Tests\Fixtures\TestApplication;
use Perfbase\Yii1\Tests\Fixtures\TestHttpRequest;
use Perfbase\Yii1\Tests\Fixtures\TestPerfbaseClientProvider;
use Perfbase\Yii1\Tests\Fixtures\TestPerfbaseComponent;
use Perfbase\Yii1\Tests\Fixtures\TestUrlManager;
use Perfbase\Yii1\Tests\Fixtures\TestWebUser;
use Perfbase\Yii1\Tests\Fixtures\YiiState;
use PHPUnit\Framework\TestCase;

class PerfbaseBootstrapTest extends TestCase
{
    protected function tearDown(): void
    {
        TestPerfbaseClientProvider::$client = null;
        YiiState::resetApplication();
        unset($_SERVER['argv']);
        parent::tearDown();
    }

    public function test_bootstrap_is_idempotent(): void
    {
        $client = new RecordingPerfbaseClient();
        TestPerfbaseClientProvider::$client = $client;
        $app = $this->createWebApplication();
        $component = $this->getPerfbaseComponent($app);

        $component->bootstrap($app);
        $component->bootstrap($app);

        $app->onBeginRequest(new \CEvent($app));
        $app->onEndRequest(new \CEvent($app));

        self::assertSame(['http.GET./site/index'], $client->startedSpans);
        self::assertSame(1, $client->submitCalls);
    }

    public function test_end_request_without_active_lifecycle_noops(): void
    {
        $client = new RecordingPerfbaseClient();
        TestPerfbaseClientProvider::$client = $client;
        $app = $this->createWebApplication();

        $this->getPerfbaseComponent($app)->bootstrap($app);
        $app->onEndRequest(new \CEvent($app));

        self::assertSame([], $client->startedSpans);
        self::assertSame(0, $client->submitCalls);
    }

    public function test_console_error_marks_failure_and_submits(): void
    {
        $client = new RecordingPerfbaseClient();
        TestPerfbaseClientProvider::$client = $client;
        $_SERVER['argv'] = ['yiic', 'cache', 'flush'];
        $app = $this->createConsoleApplication();

        $app->onBeginRequest(new \CEvent($app));
        $app->onError(new \CErrorEvent($app, (string) E_WARNING, 'console failed', __FILE__, __LINE__));
        $app->onEndRequest(new \CEvent($app));

        self::assertSame(['console.cache/flush'], $client->startedSpans);
        self::assertSame('console failed', $client->attributes['exception']);
        self::assertSame('1', $client->attributes['exit_code']);
    }

    public function test_cron_command_uses_cron_lifecycle(): void
    {
        $client = new RecordingPerfbaseClient();
        TestPerfbaseClientProvider::$client = $client;
        $_SERVER['argv'] = ['yiic', 'schedule', 'run'];
        $app = $this->createConsoleApplication([
            'include' => ['http' => ['*'], 'console' => ['*'], 'cron' => ['schedule/*']],
        ]);

        $app->onBeginRequest(new \CEvent($app));
        $app->onEndRequest(new \CEvent($app));

        self::assertSame(['cron.schedule/run'], $client->startedSpans);
        self::assertSame('cron', $client->attributes['source']);
    }

    public function test_web_route_resolution_falls_back_to_request_path(): void
    {
        $client = new RecordingPerfbaseClient();
        TestPerfbaseClientProvider::$client = $client;
        $app = $this->createWebApplication([], ServerFallbackRequest::class, true);
        $this->getPerfbaseComponent($app)->bootstrap($app);

        $app->onBeginRequest(new \CEvent($app));
        $app->onEndRequest(new \CEvent($app));

        self::assertSame(['http.GET./server/fallback'], $client->startedSpans);
    }

    public function test_non_application_sender_is_ignored(): void
    {
        $component = new TestPerfbaseComponent();
        $component->enabled = true;
        $component->api_key = 'test-key';
        $bootstrap = new \Perfbase\Yii1\PerfbaseBootstrap($component);

        $bootstrap->handleBeginRequest(new \CEvent(new \stdClass()));
        $bootstrap->handleException(new \CExceptionEvent(new \stdClass(), new \CException('ignored')));
        $bootstrap->handleError(new \CErrorEvent(new \stdClass(), (string) E_WARNING, 'ignored', __FILE__, __LINE__));
        $bootstrap->handleEndRequest(new \CEvent(new \stdClass()));

        $this->addToAssertionCount(1);
    }

    public function test_unsupported_application_type_is_ignored(): void
    {
        $component = new TestPerfbaseComponent();
        $component->enabled = true;
        $component->api_key = 'test-key';
        $bootstrap = new \Perfbase\Yii1\PerfbaseBootstrap($component);
        $app = new TestApplication([
            'basePath' => dirname(__DIR__, 2),
        ]);

        $bootstrap->handleBeginRequest(new \CEvent($app));
        $bootstrap->handleEndRequest(new \CEvent($app));

        $this->addToAssertionCount(1);
    }

    public function test_resolve_console_command_ignores_options_and_limits_segments(): void
    {
        $bootstrap = new \Perfbase\Yii1\PerfbaseBootstrap($this->makeComponent());
        $_SERVER['argv'] = ['yiic', '--verbose', 'cache', 'flush', 'extra'];

        $result = $this->invokePrivate($bootstrap, 'resolveConsoleCommand');

        self::assertSame('cache/flush', $result);
    }

    public function test_resolve_console_command_returns_unknown_when_no_command_exists(): void
    {
        $bootstrap = new \Perfbase\Yii1\PerfbaseBootstrap($this->makeComponent());
        $_SERVER['argv'] = ['yiic', '--verbose'];

        $result = $this->invokePrivate($bootstrap, 'resolveConsoleCommand');

        self::assertSame('unknown', $result);
    }

    public function test_is_cron_command_returns_false_without_include_filters(): void
    {
        $bootstrap = new \Perfbase\Yii1\PerfbaseBootstrap($this->makeComponent());

        self::assertFalse($this->invokePrivate($bootstrap, 'isCronCommand', ['schedule/run']));
    }

    public function test_is_cron_command_returns_true_for_matching_filter(): void
    {
        $component = $this->makeComponent();
        $component->include = ['http' => ['*'], 'console' => ['*'], 'cron' => ['schedule/*']];
        $bootstrap = new \Perfbase\Yii1\PerfbaseBootstrap($component);

        self::assertTrue($this->invokePrivate($bootstrap, 'isCronCommand', ['schedule/run']));
    }

    public function test_resolve_http_status_code_prefers_explicit_status(): void
    {
        $bootstrap = new \Perfbase\Yii1\PerfbaseBootstrap($this->makeComponent());
        $reflection = new \ReflectionProperty($bootstrap, 'httpStatusCode');
        $reflection->setAccessible(true);
        $reflection->setValue($bootstrap, 418);

        $result = $this->invokePrivate($bootstrap, 'resolveHttpStatusCode');

        self::assertSame(418, $result);
    }

    public function test_resolve_http_status_code_defaults_to_200_when_none_is_set(): void
    {
        $bootstrap = new \Perfbase\Yii1\PerfbaseBootstrap($this->makeComponent());

        self::assertSame(200, $this->invokePrivate($bootstrap, 'resolveHttpStatusCode'));
    }

    public function test_request_path_falls_back_to_path_info_when_url_and_request_uri_are_empty(): void
    {
        $bootstrap = new \Perfbase\Yii1\PerfbaseBootstrap($this->makeComponent());
        $request = new \Perfbase\Yii1\Tests\Fixtures\PathInfoOnlyRequest();

        $result = $this->invokePrivate($bootstrap, 'requestPath', [$request]);

        self::assertSame('pathinfo-only', $result);
    }

    public function test_resolve_web_route_returns_parsed_route_when_available(): void
    {
        $bootstrap = new \Perfbase\Yii1\PerfbaseBootstrap($this->makeComponent());
        $app = $this->createWebApplication();

        self::assertSame('site/index', $this->invokePrivate($bootstrap, 'resolveWebRoute', [$app]));
    }

    public function test_resolve_web_route_falls_back_to_request_path_when_parse_fails(): void
    {
        $bootstrap = new \Perfbase\Yii1\PerfbaseBootstrap($this->makeComponent());
        $app = $this->createWebApplication([], ServerFallbackRequest::class, true);

        self::assertSame('/server/fallback', $this->invokePrivate($bootstrap, 'resolveWebRoute', [$app]));
    }

    /**
     * @param array<string, mixed> $perfbaseConfig
     * @param class-string<TestHttpRequest> $requestClass
     */
    private function createWebApplication(
        array $perfbaseConfig = [],
        string $requestClass = TestHttpRequest::class,
        bool $throwParse = false
    ): \CWebApplication
    {
        return new \CWebApplication([
            'basePath' => dirname(__DIR__, 2),
            'preload' => ['perfbase'],
            'components' => [
                'request' => [
                    'class' => $requestClass,
                ],
                'urlManager' => [
                    'class' => TestUrlManager::class,
                    'testRoute' => 'site/index',
                    'throwParse' => $throwParse,
                ],
                'user' => [
                    'class' => TestWebUser::class,
                ],
                'perfbase' => array_merge([
                    'class' => TestPerfbaseComponent::class,
                    'enabled' => true,
                    'sample_rate' => 1.0,
                    'api_key' => 'test-key',
                    'app_version' => '1.2.3',
                ], $perfbaseConfig),
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $perfbaseConfig
     */
    private function createConsoleApplication(array $perfbaseConfig = []): \CConsoleApplication
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
                    'app_version' => '1.2.3',
                    'include' => ['http' => ['*'], 'console' => ['*'], 'cron' => []],
                    'exclude' => ['http' => [], 'console' => [], 'cron' => []],
                ], $perfbaseConfig),
            ],
        ]);
    }

    private function getPerfbaseComponent(\CWebApplication $app): PerfbaseComponent
    {
        /** @var PerfbaseComponent */
        return $app->getComponent('perfbase');
    }

    private function makeComponent(): TestPerfbaseComponent
    {
        $component = new TestPerfbaseComponent();
        $component->enabled = true;
        $component->api_key = 'test-key';
        $component->sample_rate = 1.0;
        $component->include = ['http' => ['*'], 'console' => ['*'], 'cron' => []];
        $component->exclude = ['http' => [], 'console' => [], 'cron' => []];

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
