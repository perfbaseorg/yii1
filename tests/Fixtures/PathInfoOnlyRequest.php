<?php

declare(strict_types=1);

namespace Perfbase\Yii1\Tests\Fixtures;

class PathInfoOnlyRequest extends TestHttpRequest
{
    public string $testUrl = '';
    public string $testPathInfo = 'pathinfo-only';
    public string $testRequestUri = '';
}
