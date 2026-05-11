<?php

declare(strict_types=1);

namespace Perfbase\Yii1\Support;

class SpanNaming
{
    public static function forHttp(string $method, string $routeOrPath): string
    {
        return 'http';
    }

    public static function forConsole(string $command): string
    {
        return 'artisan';
    }

    public static function forCron(string $command): string
    {
        return 'cron';
    }
}
