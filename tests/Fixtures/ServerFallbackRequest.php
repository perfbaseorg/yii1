<?php

declare(strict_types=1);

namespace Perfbase\Yii1\Tests\Fixtures;

class ServerFallbackRequest extends TestHttpRequest
{
    public string $testUrl = '';
    public string $testPathInfo = '';
    public string $testRequestUri = '/server/fallback?token=secret';
}
