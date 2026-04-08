<?php

declare(strict_types=1);

namespace Perfbase\Yii1\Support;

use Perfbase\SDK\Config;
use Perfbase\SDK\Perfbase;

class PerfbaseClientProvider
{
    /** @var array<string, mixed> */
    private array $config;

    private PerfbaseErrorHandler $errorHandler;

    /** @var callable */
    private $clientFactory;

    private ?Perfbase $client = null;
    private bool $initialized = false;

    /**
     * @param array<string, mixed> $config
     * @param callable|null $clientFactory
     */
    public function __construct(array $config, PerfbaseErrorHandler $errorHandler, $clientFactory = null)
    {
        $this->config = $config;
        $this->errorHandler = $errorHandler;
        $this->clientFactory = $clientFactory ?? static function (Config $config): Perfbase {
            return new Perfbase($config);
        };
    }

    public function getClient(): ?Perfbase
    {
        if ($this->initialized) {
            return $this->client;
        }

        $this->initialized = true;

        try {
            $sdkConfig = Config::fromArray([
                'api_key' => (string) ($this->config['api_key'] ?? ''),
                'api_url' => (string) ($this->config['api_url'] ?? 'https://receiver.perfbase.com'),
                'flags' => (int) ($this->config['flags'] ?? 0),
                'timeout' => (int) ($this->config['timeout'] ?? 10),
                'proxy' => $this->normalizeProxy($this->config['proxy'] ?? null),
            ]);

            $factory = $this->clientFactory;
            $client = $factory($sdkConfig);

            if (!$client instanceof Perfbase) {
                throw new \RuntimeException('Perfbase client factory must return a Perfbase instance.');
            }

            $this->client = $client;
        } catch (\Throwable $throwable) {
            $this->client = null;
            $this->errorHandler->handle($throwable, 'sdk_init');
        }

        return $this->client;
    }

    /**
     * @param mixed $proxy
     */
    private function normalizeProxy($proxy): ?string
    {
        if (!is_string($proxy) || $proxy === '') {
            return null;
        }

        return $proxy;
    }
}
