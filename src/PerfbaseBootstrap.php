<?php

declare(strict_types=1);

namespace Perfbase\Yii1;

use Perfbase\Yii1\Lifecycle\ConsoleCommandLifecycle;
use Perfbase\Yii1\Lifecycle\CronCommandLifecycle;
use Perfbase\Yii1\Lifecycle\HttpRequestLifecycle;
use Perfbase\Yii1\Profiling\AbstractProfiler;
use Perfbase\Yii1\Support\FilterMatcher;

class PerfbaseBootstrap
{
    private PerfbaseComponent $component;
    private ?AbstractProfiler $activeLifecycle = null;
    private bool $bootstrapped = false;
    private bool $consoleFailure = false;
    private ?int $httpStatusCode = null;

    public function __construct(PerfbaseComponent $component)
    {
        $this->component = $component;
    }

    public function bootstrap(\CApplication $app): void
    {
        if ($this->bootstrapped) {
            return;
        }

        $this->bootstrapped = true;

        $app->attachEventHandler('onBeginRequest', [$this, 'handleBeginRequest']);
        $app->attachEventHandler('onEndRequest', [$this, 'handleEndRequest']);
        $app->attachEventHandler('onException', [$this, 'handleException']);
        $app->attachEventHandler('onError', [$this, 'handleError']);
    }

    public function handleBeginRequest(\CEvent $event): void
    {
        if ($this->activeLifecycle !== null) {
            return;
        }

        $this->consoleFailure = false;
        $this->httpStatusCode = null;

        $app = $event->sender;
        if (!$app instanceof \CApplication) {
            return;
        }

        try {
            if ($app instanceof \CWebApplication) {
                $this->activeLifecycle = new HttpRequestLifecycle($app, $this->resolveWebRoute($app), $this->component);
            } elseif ($app instanceof \CConsoleApplication) {
                $command = $this->resolveConsoleCommand();
                if ($this->isCronCommand($command)) {
                    $this->activeLifecycle = new CronCommandLifecycle($command, $this->component);
                } else {
                    $this->activeLifecycle = new ConsoleCommandLifecycle($command, $this->component);
                }
            } else {
                return;
            }

            $this->activeLifecycle->startProfiling();
        } catch (\Throwable $throwable) {
            $this->activeLifecycle = null;
            $this->component->getErrorHandler()->handle($throwable, 'yii1_begin_request');
        }
    }

    public function handleException(\CExceptionEvent $event): void
    {
        if ($this->activeLifecycle === null) {
            return;
        }

        try {
            $exception = $event->exception;
            $this->activeLifecycle->setExceptionMessage((string) $exception->getMessage());

            if ($exception instanceof \CHttpException && $this->activeLifecycle instanceof HttpRequestLifecycle) {
                $this->httpStatusCode = (int) $exception->statusCode;
            }

            if ($this->activeLifecycle instanceof ConsoleCommandLifecycle || $this->activeLifecycle instanceof CronCommandLifecycle) {
                $this->consoleFailure = true;
                $this->activeLifecycle->setExitCode(1);
            }
        } catch (\Throwable $throwable) {
            $this->component->getErrorHandler()->handle($throwable, 'yii1_exception');
        }
    }

    public function handleError(\CErrorEvent $event): void
    {
        if ($this->activeLifecycle === null) {
            return;
        }

        try {
            $this->activeLifecycle->setExceptionMessage((string) $event->message);

            if ($this->activeLifecycle instanceof HttpRequestLifecycle) {
                $this->httpStatusCode = 500;
            }

            if ($this->activeLifecycle instanceof ConsoleCommandLifecycle || $this->activeLifecycle instanceof CronCommandLifecycle) {
                $this->consoleFailure = true;
                $this->activeLifecycle->setExitCode(1);
            }
        } catch (\Throwable $throwable) {
            $this->component->getErrorHandler()->handle($throwable, 'yii1_error');
        }
    }

    public function handleEndRequest(\CEvent $event): void
    {
        if ($this->activeLifecycle === null) {
            return;
        }

        try {
            if ($this->activeLifecycle instanceof HttpRequestLifecycle) {
                $statusCode = $this->resolveHttpStatusCode();
                $this->activeLifecycle->setResponseStatusCode($statusCode);
            } elseif ($this->activeLifecycle instanceof ConsoleCommandLifecycle || $this->activeLifecycle instanceof CronCommandLifecycle) {
                $this->activeLifecycle->setExitCode($this->consoleFailure ? 1 : 0);
            }

            $this->activeLifecycle->stopProfiling();
        } catch (\Throwable $throwable) {
            $this->component->getErrorHandler()->handle($throwable, 'yii1_end_request');
        } finally {
            $this->activeLifecycle = null;
            $this->consoleFailure = false;
            $this->httpStatusCode = null;
        }
    }

    private function resolveWebRoute(\CWebApplication $app): string
    {
        try {
            return (string) $app->getUrlManager()->parseUrl($app->getRequest());
        } catch (\Throwable $throwable) {
            return $this->requestPath($app->getRequest());
        }
    }

    private function requestPath(\CHttpRequest $request): string
    {
        $urlPath = parse_url((string) $request->getUrl(), PHP_URL_PATH);
        if (is_string($urlPath) && $urlPath !== '') {
            return $urlPath;
        }

        $requestUri = $request->getRequestUri();
        $requestUriPath = parse_url((string) $requestUri, PHP_URL_PATH);
        if (is_string($requestUriPath) && $requestUriPath !== '') {
            return $requestUriPath;
        }

        return (string) $request->getPathInfo();
    }

    private function resolveConsoleCommand(): string
    {
        $argv = $_SERVER['argv'] ?? [];
        if (!is_array($argv)) {
            return 'unknown';
        }

        $parts = [];
        foreach (array_slice($argv, 1) as $arg) {
            if (!is_string($arg) || $arg === '' || strncmp($arg, '-', 1) === 0) {
                continue;
            }

            $parts[] = $arg;
            if (count($parts) === 2) {
                break;
            }
        }

        if ($parts === []) {
            return 'unknown';
        }

        return implode('/', $parts);
    }

    private function isCronCommand(string $command): bool
    {
        $config = $this->component->getConfig();
        $include = $config['include']['cron'] ?? [];
        $exclude = $config['exclude']['cron'] ?? [];

        if (!is_array($include) || $include === []) {
            return false;
        }

        $parts = explode('/', trim($command, '/'));
        $components = array_values(array_unique([
            trim($command, '/'),
            $parts[0],
        ]));

        return FilterMatcher::passesFilters(
            $components,
            array_values(array_filter($include, 'is_string')),
            is_array($exclude) ? array_values(array_filter($exclude, 'is_string')) : []
        );
    }

    private function resolveHttpStatusCode(): int
    {
        if ($this->httpStatusCode !== null) {
            return $this->httpStatusCode;
        }

        $status = http_response_code();
        if (is_int($status) && $status > 0) {
            return $status;
        }

        return 200;
    }
}
