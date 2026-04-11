<?php

declare(strict_types=1);

namespace Perfbase\Yii1\Tests\Fixtures;

use Perfbase\SDK\Perfbase;
use Perfbase\SDK\SubmitResult;

class RecordingPerfbaseClient extends Perfbase
{
    /** @var array<string, string> */
    public array $attributes = [];

    /** @var array<int, string> */
    public array $startedSpans = [];

    /** @var array<int, string> */
    public array $stoppedSpans = [];

    public bool $extensionAvailable = true;
    public bool $stopResult = true;
    public int $submitCalls = 0;
    public ?\Throwable $submitException = null;
    public ?SubmitResult $submitResult = null;

    public function __construct()
    {
    }

    public function __destruct()
    {
    }

    public function startTraceSpan(string $spanName, array $attributes = []): void
    {
        $this->startedSpans[] = $spanName;
    }

    public function stopTraceSpan(string $spanName): bool
    {
        $this->stoppedSpans[] = $spanName;

        return $this->stopResult;
    }

    public function submitTrace(): SubmitResult
    {
        $this->submitCalls++;

        if ($this->submitException !== null) {
            throw $this->submitException;
        }

        return $this->submitResult ?? SubmitResult::success();
    }

    public function reset(): void
    {
    }

    public function isExtensionAvailable(): bool
    {
        return $this->extensionAvailable;
    }

    public function setAttribute(string $key, string $value): void
    {
        $this->attributes[$key] = $value;
    }
}
