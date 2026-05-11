<?php

declare(strict_types=1);

namespace Perfbase\Yii1\Tests\Fixtures;

use Perfbase\Yii1\Profiling\AbstractProfiler;
use Perfbase\Yii1\Support\PerfbaseClientProvider;
use Perfbase\Yii1\Support\PerfbaseErrorHandler;

class TestProfiler extends AbstractProfiler
{
    private bool $shouldProfile = true;
    private bool $shouldSubmitTrace = true;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        PerfbaseClientProvider $clientProvider,
        PerfbaseErrorHandler $errorHandler,
        array $config
    ) {
        parent::__construct('test', $clientProvider, $errorHandler, $config, 'test', '1.2.3');
    }

    public function setShouldProfile(bool $shouldProfile): void
    {
        $this->shouldProfile = $shouldProfile;
    }

    public function setShouldSubmitTrace(bool $shouldSubmitTrace): void
    {
        $this->shouldSubmitTrace = $shouldSubmitTrace;
    }

    protected function shouldProfile(): bool
    {
        return $this->shouldProfile;
    }

    protected function shouldSubmitTrace(): bool
    {
        return $this->shouldSubmitTrace;
    }
}
