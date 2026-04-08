<?php

declare(strict_types=1);

namespace Perfbase\Yii1\Tests\Fixtures;

use Perfbase\Yii1\PerfbaseComponent;
use Perfbase\Yii1\Support\PerfbaseClientProvider;

class TestPerfbaseComponent extends PerfbaseComponent
{
    protected function createClientProvider(): PerfbaseClientProvider
    {
        return new TestPerfbaseClientProvider();
    }
}
