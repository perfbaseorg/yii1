<?php

declare(strict_types=1);

namespace Perfbase\Yii1\Lifecycle;

use Perfbase\SDK\Utils\EnvironmentUtils;
use Perfbase\Yii1\PerfbaseComponent;
use Perfbase\Yii1\Profiling\AbstractProfiler;
use Perfbase\Yii1\Support\FilterMatcher;
use Perfbase\Yii1\Support\SpanNaming;

class HttpRequestLifecycle extends AbstractProfiler
{
    private \CWebApplication $app;
    private string $route;
    private ?int $responseStatusCode = null;

    public function __construct(\CWebApplication $app, string $route, PerfbaseComponent $component)
    {
        parent::__construct(
            SpanNaming::forHttp($this->requestMethod($app->getRequest()), $route !== '' ? $route : $this->requestPath($app->getRequest())),
            $component->getClientProvider(),
            $component->getErrorHandler(),
            $component->getConfig(),
            $component->getEnvironment(),
            (string) ($component->getConfig()['app_version'] ?? '')
        );

        $this->app = $app;
        $this->route = $route;
    }

    public function setResponseStatusCode(int $statusCode): void
    {
        $this->responseStatusCode = $statusCode;
        $this->setAttribute('http_status_code', (string) $statusCode);
    }

    protected function shouldProfile(): bool
    {
        if (!(bool) ($this->config['enabled'] ?? false)) {
            return false;
        }

        return FilterMatcher::passesFilters(
            $this->getRequestComponents(),
            $this->normalizeFilters($this->config['include']['http'] ?? ['*']),
            $this->normalizeFilters($this->config['exclude']['http'] ?? [])
        );
    }

    protected function setDefaultAttributes(): void
    {
        parent::setDefaultAttributes();

        $request = $this->app->getRequest();
        $path = $this->requestPath($request);
        $route = $this->hasStableRoute($this->route) ? $this->normalizeHttpPath($this->route) : $path;

        $this->setAttributes([
            'source' => 'http',
            'action' => sprintf('%s %s', $this->requestMethod($request), $route),
            'http_method' => $this->requestMethod($request),
            'http_url' => $this->requestHost($request) . $path,
            'user_ip' => (string) (EnvironmentUtils::getUserIp() ?? ''),
            'user_agent' => (string) (EnvironmentUtils::getUserUserAgent() ?? ''),
        ]);

        if ($this->app->hasComponent('user')) {
            $user = $this->app->getComponent('user');
            if (is_object($user) && method_exists($user, 'getIsGuest') && !$user->getIsGuest() && method_exists($user, 'getId')) {
                $identifier = $user->getId();
                if ($identifier !== null && $identifier !== '') {
                    $this->setAttribute('user_id', (string) $identifier);
                }
            }
        }
    }

    protected function shouldSubmitTrace(): bool
    {
        if ($this->responseStatusCode === null) {
            return true;
        }

        return in_array($this->responseStatusCode, $this->profileHttpStatusCodes(), true);
    }

    /**
     * @return array<int, string>
     */
    private function getRequestComponents(): array
    {
        $request = $this->app->getRequest();
        $path = $this->requestPath($request);
        $route = $this->hasStableRoute($this->route) ? $this->normalizeHttpPath($this->route) : $path;

        return array_values(array_unique([
            $path,
            $route,
            $this->requestMethod($request) . ' ' . $path,
            $this->requestMethod($request) . ' ' . $route,
            $this->route,
        ]));
    }

    /**
     * @param mixed $filters
     * @return array<int, string>
     */
    private function normalizeFilters($filters): array
    {
        if (!is_array($filters)) {
            return [];
        }

        return array_values(array_filter($filters, static function ($filter): bool {
            return is_string($filter) && $filter !== '';
        }));
    }

    /**
     * @return array<int, int>
     */
    private function profileHttpStatusCodes(): array
    {
        $statusCodes = $this->config['profile_http_status_codes'] ?? [...range(200, 299), ...range(500, 599)];
        if (!is_array($statusCodes)) {
            return [...range(200, 299), ...range(500, 599)];
        }

        $normalized = [];

        foreach ($statusCodes as $statusCode) {
            if (is_int($statusCode)) {
                $normalized[] = $statusCode;
                continue;
            }

            if (is_string($statusCode) && ctype_digit($statusCode)) {
                $normalized[] = (int) $statusCode;
            }
        }

        return array_values(array_unique($normalized));
    }

    private function normalizeHttpPath(string $path): string
    {
        return '/' . ltrim($path, '/');
    }

    private function hasStableRoute(string $route): bool
    {
        return trim($route, '/') !== '';
    }

    private function requestMethod(\CHttpRequest $request): string
    {
        return strtoupper((string) $request->getRequestType());
    }

    private function requestPath(\CHttpRequest $request): string
    {
        $urlPath = parse_url((string) $request->getUrl(), PHP_URL_PATH);
        if (is_string($urlPath) && $urlPath !== '') {
            return $this->normalizeHttpPath($urlPath);
        }

        $requestUri = $request->getRequestUri();
        $requestUriPath = parse_url((string) $requestUri, PHP_URL_PATH);
        if (is_string($requestUriPath) && $requestUriPath !== '') {
            return $this->normalizeHttpPath($requestUriPath);
        }

        return $this->normalizeHttpPath((string) $request->getPathInfo());
    }

    private function requestHost(\CHttpRequest $request): string
    {
        return (string) $request->getHostInfo();
    }
}
