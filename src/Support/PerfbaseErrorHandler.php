<?php

declare(strict_types=1);

namespace Perfbase\Yii1\Support;

use Throwable;

class PerfbaseErrorHandler
{
    private bool $debug;
    private bool $logErrors;

    public function __construct(bool $debug, bool $logErrors)
    {
        $this->debug = $debug;
        $this->logErrors = $logErrors;
    }

    public function handle(Throwable $throwable, string $context = 'unknown'): void
    {
        if ($this->debug) {
            throw $throwable;
        }

        if ($this->logErrors) {
            error_log(sprintf('Perfbase profiling error in %s: %s', $context, $throwable->getMessage()));
        }
    }
}
