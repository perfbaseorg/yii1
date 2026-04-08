<?php

declare(strict_types=1);

namespace Perfbase\Yii1\Lifecycle;

use Perfbase\Yii1\PerfbaseComponent;
use Perfbase\Yii1\Profiling\AbstractProfiler;
use Perfbase\Yii1\Support\FilterMatcher;
use Perfbase\Yii1\Support\SpanNaming;

class CronCommandLifecycle extends AbstractProfiler
{
    private string $command;

    public function __construct(string $command, PerfbaseComponent $component)
    {
        parent::__construct(
            SpanNaming::forCron($command),
            $component->getClientProvider(),
            $component->getErrorHandler(),
            $component->getConfig(),
            $component->getEnvironment(),
            (string) ($component->getConfig()['app_version'] ?? '')
        );

        $this->command = $this->normalizeCommand($command);
    }

    protected function shouldProfile(): bool
    {
        if (!(bool) ($this->config['enabled'] ?? false)) {
            return false;
        }

        return FilterMatcher::passesFilters(
            $this->getCommandComponents(),
            $this->normalizeFilters($this->config['include']['cron'] ?? []),
            $this->normalizeFilters($this->config['exclude']['cron'] ?? [])
        );
    }

    protected function setDefaultAttributes(): void
    {
        parent::setDefaultAttributes();

        $this->setAttributes([
            'source' => 'cron',
            'action' => $this->command,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function getCommandComponents(): array
    {
        $parts = explode('/', $this->command);

        return array_values(array_unique([
            $this->command,
            $parts[0],
        ]));
    }

    /**
     * @param mixed $filters
     * @return array<int, string>
     */
    private function normalizeFilters($filters): array
    {
        if (!is_array($filters)) {
            return [];
        }

        return array_values(array_filter($filters, static function ($filter): bool {
            return is_string($filter) && $filter !== '';
        }));
    }

    private function normalizeCommand(string $command): string
    {
        $normalized = trim($command, '/');

        return $normalized === '' ? 'unknown' : $normalized;
    }
}
