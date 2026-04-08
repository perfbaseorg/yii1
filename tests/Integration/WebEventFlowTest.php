<?php

declare(strict_types=1);

namespace Perfbase\Yii1\Tests\Integration;

use Perfbase\Yii1\Tests\Fixtures\RecordingPerfbaseClient;
use Perfbase\Yii1\Tests\Fixtures\TestHttpRequest;
use Perfbase\Yii1\Tests\Fixtures\TestPerfbaseClientProvider;
use Perfbase\Yii1\Tests\Fixtures\TestPerfbaseComponent;
use Perfbase\Yii1\Tests\Fixtures\TestUrlManager;
use Perfbase\Yii1\Tests\Fixtures\TestWebUser;
use Perfbase\Yii1\Tests\Fixtures\YiiState;
use PHPUnit\Framework\TestCase;

class WebEventFlowTest extends TestCase
{
    protected function tearDown(): void
    {
        TestPerfbaseClientProvider::$client = null;
        YiiState::resetApplication();
        parent::tearDown();
    }

    public function test_request_start_stop_submit_flow(): void
    {
        $client = new RecordingPerfbaseClient();
        TestPerfbaseClientProvider::$client = $client;
        $app = $this->createApplication();

        $app->onBeginRequest(new \CEvent($app));
        $app->onEndRequest(new \CEvent($app));

        self::assertSame(['http.GET./site/index'], $client->startedSpans);
        self::assertSame('200', $client->attributes['http_status_code']);
        self::assertSame(1, $client->submitCalls);
    }

    public function test_exception_path_still_cleans_up(): void
    {
        $client = new RecordingPerfbaseClient();
        TestPerfbaseClientProvider::$client = $client;
        $app = $this->createApplication();

        $app->onBeginRequest(new \CEvent($app));
        $app->onException(new \CExceptionEvent($app, new \CHttpException(404, 'missing')));
        $app->onEndRequest(new \CEvent($app));

        self::assertSame('missing', $client->attributes['exception']);
        self::assertSame('404', $client->attributes['http_status_code']);
        self::assertSame(1, $client->submitCalls);
    }

    public function test_error_path_still_cleans_up(): void
    {
        $client = new RecordingPerfbaseClient();
        TestPerfbaseClientProvider::$client = $client;
        $app = $this->createApplication();

        $app->onBeginRequest(new \CEvent($app));
        $app->onError(new \CErrorEvent($app, (string) E_WARNING, 'boom', __FILE__, __LINE__));
        $app->onEndRequest(new \CEvent($app));

        self::assertSame('boom', $client->attributes['exception']);
        self::assertSame('500', $client->attributes['http_status_code']);
        self::assertSame(1, $client->submitCalls);
    }

    public function test_disabled_state_results_in_no_profiling(): void
    {
        $client = new RecordingPerfbaseClient();
        TestPerfbaseClientProvider::$client = $client;
        $app = $this->createApplication(['enabled' => false]);

        $app->onBeginRequest(new \CEvent($app));
        $app->onEndRequest(new \CEvent($app));

        self::assertSame([], $client->startedSpans);
        self::assertSame(0, $client->submitCalls);
    }

    /**
     * @param array<string, mixed> $perfbaseConfig
     */
    private function createApplication(array $perfbaseConfig = []): \CWebApplication
    {
        return new \CWebApplication([
            'basePath' => dirname(__DIR__, 2),
            'preload' => ['perfbase'],
            'components' => [
                'request' => [
                    'class' => TestHttpRequest::class,
                ],
                'urlManager' => [
                    'class' => TestUrlManager::class,
                    'testRoute' => 'site/index',
                ],
                'user' => [
                    'class' => TestWebUser::class,
                ],
                'perfbase' => array_merge([
                    'class' => TestPerfbaseComponent::class,
                    'enabled' => true,
                    'sample_rate' => 1.0,
                    'api_key' => 'test-key',
                    'app_version' => 'test-suite',
                ], $perfbaseConfig),
            ],
        ]);
    }
}
