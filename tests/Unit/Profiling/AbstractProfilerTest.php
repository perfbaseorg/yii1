<?php

declare(strict_types=1);

namespace Perfbase\Yii1\Tests\Unit\Profiling;

use Perfbase\SDK\SubmitResult;
use Perfbase\Yii1\Support\PerfbaseClientProvider;
use Perfbase\Yii1\Support\PerfbaseErrorHandler;
use Perfbase\Yii1\Tests\Fixtures\RecordingPerfbaseClient;
use Perfbase\Yii1\Tests\Fixtures\TestProfiler;
use PHPUnit\Framework\TestCase;

class AbstractProfilerTest extends TestCase
{
    public function test_start_profiling_is_idempotent_and_sets_defaults(): void
    {
        $client = new RecordingPerfbaseClient();
        $profiler = new TestProfiler($this->makeProvider($client), new PerfbaseErrorHandler(false, false), ['sample_rate' => 1.0]);

        $profiler->startProfiling();
        $profiler->startProfiling();
        $profiler->stopProfiling();

        self::assertSame(['test.span'], $client->startedSpans);
        self::assertArrayHasKey('hostname', $client->attributes);
        self::assertSame('test', $client->attributes['environment']);
        self::assertSame('1.2.3', $client->attributes['app_version']);
    }

    public function test_start_profiling_noops_when_client_is_unavailable(): void
    {
        $profiler = new TestProfiler(
            new PerfbaseClientProvider(['api_key' => '', 'api_url' => 'https://receiver.perfbase.local'], new PerfbaseErrorHandler(false, false)),
            new PerfbaseErrorHandler(false, false),
            ['sample_rate' => 1.0]
        );

        $profiler->startProfiling();

        self::assertFalse($profiler->hasStarted());
    }

    public function test_start_profiling_respects_zero_sample_rate(): void
    {
        $client = new RecordingPerfbaseClient();
        $profiler = new TestProfiler($this->makeProvider($client), new PerfbaseErrorHandler(false, false), ['sample_rate' => 0.0]);

        $profiler->startProfiling();

        self::assertFalse($profiler->hasStarted());
        self::assertSame([], $client->startedSpans);
    }

    public function test_start_profiling_stops_when_should_profile_is_false(): void
    {
        $client = new RecordingPerfbaseClient();
        $profiler = new TestProfiler($this->makeProvider($client), new PerfbaseErrorHandler(false, false), ['sample_rate' => 1.0]);
        $profiler->setShouldProfile(false);

        $profiler->startProfiling();

        self::assertFalse($profiler->hasStarted());
        self::assertSame([], $client->startedSpans);
    }

    public function test_start_profiling_stops_when_extension_is_unavailable(): void
    {
        $client = new RecordingPerfbaseClient();
        $client->extensionAvailable = false;

        $profiler = new TestProfiler($this->makeProvider($client), new PerfbaseErrorHandler(false, false), ['sample_rate' => 1.0]);
        $profiler->startProfiling();

        self::assertFalse($profiler->hasStarted());
        self::assertSame([], $client->startedSpans);
    }

    public function test_invalid_sample_rate_throws_in_debug_mode(): void
    {
        $client = new RecordingPerfbaseClient();
        $profiler = new TestProfiler($this->makeProvider($client), new PerfbaseErrorHandler(true, true), ['sample_rate' => 'invalid']);

        $this->expectException(\RuntimeException::class);
        $profiler->startProfiling();
    }

    public function test_stop_profiling_noops_when_not_started(): void
    {
        $client = new RecordingPerfbaseClient();
        $profiler = new TestProfiler($this->makeProvider($client), new PerfbaseErrorHandler(false, false), ['sample_rate' => 1.0]);
        $profiler->stopProfiling();

        self::assertSame(0, $client->submitCalls);
    }

    public function test_stop_profiling_does_not_submit_when_span_was_not_stopped(): void
    {
        $client = new RecordingPerfbaseClient();
        $client->stopResult = false;
        $profiler = new TestProfiler($this->makeProvider($client), new PerfbaseErrorHandler(false, false), ['sample_rate' => 1.0]);

        $profiler->startProfiling();
        $profiler->stopProfiling();

        self::assertSame(0, $client->submitCalls);
        self::assertFalse($profiler->hasStarted());
    }

    public function test_stop_profiling_handles_submit_failure_results(): void
    {
        $client = new RecordingPerfbaseClient();
        $client->submitResult = SubmitResult::retryableFailure(500, 'temporary failure');
        $profiler = new TestProfiler($this->makeProvider($client), new PerfbaseErrorHandler(false, false), ['sample_rate' => 1.0]);

        $profiler->startProfiling();
        $profiler->stopProfiling();

        self::assertSame(1, $client->submitCalls);
        self::assertFalse($profiler->hasStarted());
    }

    public function test_stop_profiling_rethrows_submit_exceptions_in_debug_mode(): void
    {
        $client = new RecordingPerfbaseClient();
        $client->submitException = new \RuntimeException('submit exploded');
        $profiler = new TestProfiler($this->makeProvider($client), new PerfbaseErrorHandler(true, true), ['sample_rate' => 1.0]);

        $profiler->startProfiling();

        $this->expectException(\RuntimeException::class);
        $profiler->stopProfiling();
    }

    private function makeProvider(RecordingPerfbaseClient $client): PerfbaseClientProvider
    {
        return new PerfbaseClientProvider(
            [
                'api_key' => 'test-key',
                'api_url' => 'https://receiver.perfbase.local',
                'flags' => 0,
                'timeout' => 10,
                'proxy' => null,
            ],
            new PerfbaseErrorHandler(false, false),
            static function () use ($client): RecordingPerfbaseClient {
                return $client;
            }
        );
    }
}
