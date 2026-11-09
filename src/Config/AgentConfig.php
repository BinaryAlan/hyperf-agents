<?php

declare(strict_types=1);

namespace HyperfAgents\Config;

final class AgentConfig
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(private array $config = [])
    {
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        return new self($config);
    }

    public function getDefaultDriver(): string
    {
        return (string) ($this->config['default'] ?? 'openai');
    }

    /**
     * @return array{
     *     api_key: string,
     *     base_url: string,
     *     default_model: string,
     *     timeout: float,
     *     client_options: array<string, mixed>
     * }
     */
    public function getDriverConfig(?string $name = null): array
    {
        $driverName = $name ?? $this->getDefaultDriver();
        /** @var array<string, mixed> $driverData */
        $driverData = $this->config['drivers'][$driverName] ?? [];

        return [
            'api_key' => (string) ($driverData['api_key'] ?? ''),
            'base_url' => (string) ($driverData['base_url'] ?? 'https://api.openai.com/v1'),
            'default_model' => (string) ($driverData['default_model'] ?? 'gpt-4o'),
            'timeout' => (float) ($driverData['timeout'] ?? 60.0),
            'client_options' => is_array($driverData['client_options'] ?? null)
                ? $driverData['client_options']
                : [],
        ];
    }

    public function getDefaultModel(): string
    {
        return $this->getDriverConfig()['default_model'];
    }

    public function getMaxToolConcurrency(): int
    {
        return (int) ($this->config['concurrency']['max_tool_concurrency'] ?? 20);
    }

    /**
     * @return array{
     *     enabled: bool,
     *     max_attempts: int,
     *     initial_delay_ms: int,
     *     multiplier: float
     * }
     */
    public function getRetryConfig(): array
    {
        /** @var array<string, mixed> $retry */
        $retry = $this->config['resilience']['retry'] ?? [];

        return [
            'enabled' => (bool) ($retry['enabled'] ?? true),
            'max_attempts' => (int) ($retry['max_attempts'] ?? 3),
            'initial_delay_ms' => (int) ($retry['initial_delay_ms'] ?? 200),
            'multiplier' => (float) ($retry['multiplier'] ?? 2.0),
        ];
    }

    /**
     * @return array{
     *     enabled: bool,
     *     failure_threshold: int,
     *     recovery_time: float
     * }
     */
    public function getCircuitBreakerConfig(): array
    {
        /** @var array<string, mixed> $cb */
        $cb = $this->config['resilience']['circuit_breaker'] ?? [];

        return [
            'enabled' => (bool) ($cb['enabled'] ?? true),
            'failure_threshold' => (int) ($cb['failure_threshold'] ?? 5),
            'recovery_time' => (float) ($cb['recovery_time'] ?? 30.0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getMemoryConfig(): array
    {
        return is_array($this->config['memory'] ?? null) ? $this->config['memory'] : [];
    }
}
