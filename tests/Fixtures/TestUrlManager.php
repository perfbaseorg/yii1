<?php

declare(strict_types=1);

namespace Perfbase\Yii1\Tests\Fixtures;

class TestUrlManager extends \CUrlManager
{
    public string $testRoute = 'site/index';
    public bool $throwParse = false;

    /**
     * @param \CHttpRequest $request
     * @return string
     */
    public function parseUrl($request)
    {
        if ($this->throwParse) {
            throw new \RuntimeException('parse failure');
        }

        return $this->testRoute;
    }
}
