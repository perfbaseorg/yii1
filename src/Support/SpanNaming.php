<?php

declare(strict_types=1);

namespace Perfbase\Yii1\Support;

class SpanNaming
{
    public static function forHttp(string $method, string $routeOrPath): string
    {
        return sprintf('http.%s.%s', strtoupper($method), self::normalizeHttpPath($routeOrPath));
    }

    public static function forConsole(string $command): string
    {
        return sprintf('console.%s', self::normalizeCommand($command));
    }

    public static function forCron(string $command): string
    {
        return sprintf('cron.%s', self::normalizeCommand($command));
    }

    private static function normalizeHttpPath(string $path): string
    {
        $trimmed = ltrim($path, '/');

        return '/' . $trimmed;
    }

    private static function normalizeCommand(string $command): string
    {
        $normalized = trim($command, '/');

        return $normalized === '' ? 'unknown' : $normalized;
    }
}
