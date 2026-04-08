<?php

declare(strict_types=1);

namespace Perfbase\Yii1\Tests\Unit\Support;

use Perfbase\Yii1\Support\PerfbaseErrorHandler;
use PHPUnit\Framework\TestCase;

class PerfbaseErrorHandlerTest extends TestCase
{
    public function test_debug_mode_rethrows(): void
    {
        $handler = new PerfbaseErrorHandler(true, true);

        $this->expectException(\RuntimeException::class);
        $handler->handle(new \RuntimeException('boom'));
    }

    public function test_non_debug_mode_swallows(): void
    {
        $handler = new PerfbaseErrorHandler(false, false);
        $handler->handle(new \RuntimeException('boom'));

        $this->addToAssertionCount(1);
    }

    public function test_non_debug_mode_logs_when_enabled(): void
    {
        $handler = new PerfbaseErrorHandler(false, true);
        $logFile = tempnam(sys_get_temp_dir(), 'perfbase-yii1-log');
        if ($logFile === false) {
            self::fail('Failed to create temporary log file.');
        }

        $previousErrorLog = ini_get('error_log');
        ini_set('error_log', $logFile);

        try {
            $handler->handle(new \RuntimeException('logged boom'), 'coverage');
            $contents = file_get_contents($logFile);
        } finally {
            ini_set('error_log', $previousErrorLog === false ? '' : $previousErrorLog);
            @unlink($logFile);
        }

        self::assertIsString($contents);
        self::assertStringContainsString('Perfbase profiling error in coverage: logged boom', $contents);
    }
}
