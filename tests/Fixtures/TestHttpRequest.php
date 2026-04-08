<?php

declare(strict_types=1);

namespace Perfbase\Yii1\Tests\Fixtures;

class TestHttpRequest extends \CHttpRequest
{
    public string $testUrl = '/articles/42?token=secret';
    public string $testPathInfo = 'articles/42';
    public string $testHostInfo = 'https://example.com';
    public string $testRequestType = 'GET';
    public string $testRequestUri = '/articles/42?token=secret';

    public function getUrl(): string
    {
        return $this->testUrl;
    }

    public function getPathInfo(): string
    {
        return $this->testPathInfo;
    }

    /**
     * @param mixed $schema
     */
    public function getHostInfo($schema = ''): string
    {
        return $this->testHostInfo;
    }

    public function getRequestType(): string
    {
        return $this->testRequestType;
    }

    public function getRequestUri(): string
    {
        return $this->testRequestUri;
    }
}
