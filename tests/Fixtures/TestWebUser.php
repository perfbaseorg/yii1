<?php

declare(strict_types=1);

namespace Perfbase\Yii1\Tests\Fixtures;

class TestWebUser extends \CWebUser
{
    public bool $testGuest = true;
    public ?string $testId = null;

    public function getIsGuest(): bool
    {
        return $this->testGuest;
    }

    /**
     * @return string|null
     */
    public function getId()
    {
        return $this->testId;
    }
}
