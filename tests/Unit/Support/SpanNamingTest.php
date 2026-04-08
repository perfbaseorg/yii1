<?php

declare(strict_types=1);

namespace Perfbase\Yii1\Tests\Unit\Support;

use Perfbase\Yii1\Support\SpanNaming;
use PHPUnit\Framework\TestCase;

class SpanNamingTest extends TestCase
{
    public function test_http_span_name_is_normalized(): void
    {
        self::assertSame('http.GET./site/index', SpanNaming::forHttp('get', 'site/index'));
    }

    public function test_console_span_name_is_stable(): void
    {
        self::assertSame('console.cache/flush', SpanNaming::forConsole('cache/flush'));
    }

    public function test_cron_span_name_is_stable(): void
    {
        self::assertSame('cron.schedule/run', SpanNaming::forCron('schedule/run'));
    }

    public function test_console_unknown_fallback(): void
    {
        self::assertSame('console.unknown', SpanNaming::forConsole('/'));
    }
}
