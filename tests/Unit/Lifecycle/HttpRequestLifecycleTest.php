<?php

declare(strict_types=1);

namespace Perfbase\Yii1\Tests\Unit\Lifecycle;

use Perfbase\Yii1\Lifecycle\HttpRequestLifecycle;
use Perfbase\Yii1\PerfbaseComponent;
use Perfbase\Yii1\Tests\Fixtures\PathInfoOnlyRequest;
use Perfbase\Yii1\Tests\Fixtures\RecordingPerfbaseClient;
use Perfbase\Yii1\Tests\Fixtures\ServerFallbackRequest;
use Perfbase\Yii1\Tests\Fixtures\TestHttpRequest;
use Perfbase\Yii1\Tests\Fixtures\TestPerfbaseClientProvider;
use Perfbase\Yii1\Tests\Fixtures\TestPerfbaseComponent;
use Perfbase\Yii1\Tests\Fixtures\TestUrlManager;
use Perfbase\Yii1\Tests\Fixtures\TestWebUser;
use Perfbase\Yii1\Tests\Fixtures\YiiState;
use PHPUnit\Framework\TestCase;

class HttpRequestLifecycleTest extends TestCase
{
    protected function tearDown(): void
    {
        TestPerfbaseClientProvider::$client = null;
        YiiState::resetApplication();
        parent::tearDown();
    }

    public function test_start_and_stop_profile_http_request(): void
    {
        $client = new RecordingPerfbaseClient();
        TestPerfbaseClientProvider::$client = $client;

        $app = $this->createWebApplication();
        /** @var TestWebUser $user */
        $user = $app->getComponent('user');
        $user->testGuest = false;
        $user->testId = 'user-123';

        $lifecycle = new HttpRequestLifecycle($app, 'articles/view', $this->getPerfbaseComponent($app));
        $lifecycle->startProfiling();
        $lifecycle->setResponseStatusCode(201);
        $lifecycle->stopProfiling();

        self::assertSame(['http'], $client->startedSpans);
        self::assertSame(['http'], $client->stoppedSpans);
        self::assertSame(1, $client->submitCalls);
        self::assertSame('GET /articles/view', $client->attributes['action']);
        self::assertSame('https://example.com/articles/42', $client->attributes['http_url']);
        self::assertSame('201', $client->attributes['http_status_code']);
        self::assertSame('user-123', $client->attributes['user_id']);
        self::assertSame('http', $client->attributes['source']);
    }

    public function test_disallowed_http_status_code_is_not_submitted_by_default(): void
    {
        $client = new RecordingPerfbaseClient();
        TestPerfbaseClientProvider::$client = $client;

        $app = $this->createWebApplication();
        $lifecycle = new HttpRequestLifecycle($app, 'articles/view', $this->getPerfbaseComponent($app));
        $lifecycle->startProfiling();
        $lifecycle->setResponseStatusCode(404);
        $lifecycle->stopProfiling();

        self::assertSame(0, $client->submitCalls);
        self::assertSame(1, $client->resetCalls);
        self::assertSame('404', $client->attributes['http_status_code']);
    }

    public function test_server_error_status_code_is_submitted_by_default(): void
    {
        $client = new RecordingPerfbaseClient();
        TestPerfbaseClientProvider::$client = $client;

        $app = $this->createWebApplication();
        $lifecycle = new HttpRequestLifecycle($app, 'articles/view', $this->getPerfbaseComponent($app));
        $lifecycle->startProfiling();
        $lifecycle->setResponseStatusCode(503);
        $lifecycle->stopProfiling();

        self::assertSame(1, $client->submitCalls);
        self::assertSame(0, $client->resetCalls);
        self::assertSame('503', $client->attributes['http_status_code']);
    }

    public function test_custom_allowed_http_status_code_is_submitted(): void
    {
        $client = new RecordingPerfbaseClient();
        TestPerfbaseClientProvider::$client = $client;

        $app = $this->createWebApplication([
            'profile_http_status_codes' => [200, 404],
        ]);

        $lifecycle = new HttpRequestLifecycle($app, 'articles/view', $this->getPerfbaseComponent($app));
        $lifecycle->startProfiling();
        $lifecycle->setResponseStatusCode(404);
        $lifecycle->stopProfiling();

        self::assertSame(1, $client->submitCalls);
        self::assertSame(0, $client->resetCalls);
    }

    public function test_excluded_http_request_is_not_profiled(): void
    {
        $client = new RecordingPerfbaseClient();
        TestPerfbaseClientProvider::$client = $client;

        $app = $this->createWebApplication([
            'exclude' => ['http' => ['/articles/*'], 'console' => [], 'cron' => []],
        ]);

        $lifecycle = new HttpRequestLifecycle($app, 'articles/view', $this->getPerfbaseComponent($app));
        $lifecycle->startProfiling();
        $lifecycle->stopProfiling();

        self::assertSame([], $client->startedSpans);
        self::assertSame(0, $client->submitCalls);
    }

    public function test_disabled_http_profiling_is_not_started(): void
    {
        $client = new RecordingPerfbaseClient();
        TestPerfbaseClientProvider::$client = $client;

        $app = $this->createWebApplication();
        $component = $this->getPerfbaseComponent($app);
        $component->enabled = false;
        $lifecycle = new HttpRequestLifecycle($app, 'articles/view', $component);

        $lifecycle->startProfiling();

        self::assertFalse($lifecycle->hasStarted());
        self::assertSame([], $client->startedSpans);
    }

    public function test_exception_attribute_is_submitted(): void
    {
        $client = new RecordingPerfbaseClient();
        TestPerfbaseClientProvider::$client = $client;

        $app = $this->createWebApplication();
        $lifecycle = new HttpRequestLifecycle($app, 'articles/view', $this->getPerfbaseComponent($app));

        $lifecycle->startProfiling();
        $lifecycle->setException(new \RuntimeException('boom'));
        $lifecycle->stopProfiling();

        self::assertSame('boom', $client->attributes['exception']);
    }

    public function test_route_falls_back_to_request_path(): void
    {
        $client = new RecordingPerfbaseClient();
        TestPerfbaseClientProvider::$client = $client;

        $app = $this->createWebApplication([], TestHttpRequest::class);
        $lifecycle = new HttpRequestLifecycle($app, '', $this->getPerfbaseComponent($app));
        $lifecycle->startProfiling();
        $lifecycle->stopProfiling();

        self::assertSame(['http'], $client->startedSpans);
        self::assertSame('GET /articles/42', $client->attributes['action']);
    }

    public function test_server_request_uri_fallback_is_used(): void
    {
        $client = new RecordingPerfbaseClient();
        TestPerfbaseClientProvider::$client = $client;
        $app = $this->createWebApplication([], ServerFallbackRequest::class);

        $lifecycle = new HttpRequestLifecycle($app, '', $this->getPerfbaseComponent($app));
        $lifecycle->startProfiling();
        $lifecycle->stopProfiling();

        self::assertSame(['http'], $client->startedSpans);
        self::assertSame('https://example.com/server/fallback', $client->attributes['http_url']);
    }

    public function test_path_info_fallback_is_used(): void
    {
        $client = new RecordingPerfbaseClient();
        TestPerfbaseClientProvider::$client = $client;
        $app = $this->createWebApplication([], PathInfoOnlyRequest::class);

        $lifecycle = new HttpRequestLifecycle($app, '', $this->getPerfbaseComponent($app));
        $lifecycle->startProfiling();
        $lifecycle->stopProfiling();

        self::assertSame(['http'], $client->startedSpans);
        self::assertSame('https://example.com/pathinfo-only', $client->attributes['http_url']);
    }

    /**
     * @param array<string, mixed> $perfbaseConfig
     * @param class-string<TestHttpRequest> $requestClass
     */
    private function createWebApplication(array $perfbaseConfig = [], string $requestClass = TestHttpRequest::class): \CWebApplication
    {
        return new \CWebApplication([
            'basePath' => dirname(__DIR__, 2),
            'components' => [
                'request' => [
                    'class' => $requestClass,
                ],
                'urlManager' => [
                    'class' => TestUrlManager::class,
                    'testRoute' => 'articles/view',
                ],
                'user' => [
                    'class' => TestWebUser::class,
                ],
                'perfbase' => array_merge([
                    'class' => TestPerfbaseComponent::class,
                    'enabled' => true,
                    'sample_rate' => 1.0,
                    'profile_http_status_codes' => [...range(200, 299), ...range(500, 599)],
                    'api_key' => 'test-key',
                    'app_version' => '1.2.3',
                ], $perfbaseConfig),
            ],
        ]);
    }

    private function getPerfbaseComponent(\CWebApplication $app): PerfbaseComponent
    {
        /** @var PerfbaseComponent */
        return $app->getComponent('perfbase');
    }
}
