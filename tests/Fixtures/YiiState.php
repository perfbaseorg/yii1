<?php

declare(strict_types=1);

namespace Perfbase\Yii1\Tests\Fixtures;

final class YiiState
{
    public static function resetApplication(): void
    {
        $reflection = new \ReflectionClass(\YiiBase::class);
        $property = $reflection->getProperty('_app');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }
}
