<?php

declare(strict_types=1);

namespace HyperfAgents\Tests;

use HyperfAgents\Config\AgentConfig;
use HyperfAgents\Orchestrator\Swarm;
use HyperfAgents\Orchestrator\SwarmInterface;
use PHPUnit\Framework\TestCase;

class AgentConfigTest extends TestCase
{
    public function testPublishedConfigGetters(): void
    {
        /** @var array<string, mixed> $published */
        $published = require __DIR__ . '/../publish/hyperf_agents.php';
        $config = AgentConfig::fromArray($published);

        $this->assertSame('openai', $config->getDefaultDriver());
        $this->assertSame('gpt-4o', $config->getDefaultModel());
        $this->assertSame(20, $config->getMaxToolConcurrency());

        $driver = $config->getDriverConfig();
        $this->assertArrayHasKey('api_key', $driver);
        $this->assertArrayHasKey('base_url', $driver);
        $this->assertSame('gpt-4o', $driver['default_model']);
        $this->assertSame(60.0, $driver['timeout']);
        $this->assertTrue($driver['client_options']['verify'] ?? false);

        $retry = $config->getRetryConfig();
        $this->assertTrue($retry['enabled']);
        $this->assertSame(3, $retry['max_attempts']);
        $this->assertSame(200, $retry['initial_delay_ms']);
        $this->assertSame(2.0, $retry['multiplier']);

        $cb = $config->getCircuitBreakerConfig();
        $this->assertTrue($cb['enabled']);
        $this->assertSame(5, $cb['failure_threshold']);
        $this->assertSame(30.0, $cb['recovery_time']);

        $memory = $config->getMemoryConfig();
        $this->assertIsArray($memory);
        $this->assertSame('redis', $memory['driver'] ?? null);
    }

    public function testEmptyArrayDefaults(): void
    {
        $empty = AgentConfig::fromArray([]);

        $this->assertSame('openai', $empty->getDefaultDriver());
        $this->assertSame('gpt-4o', $empty->getDefaultModel());
        $this->assertSame(20, $empty->getMaxToolConcurrency());

        $driver = $empty->getDriverConfig();
        $this->assertSame('', $driver['api_key']);
        $this->assertSame('https://api.openai.com/v1', $driver['base_url']);
        $this->assertSame('gpt-4o', $driver['default_model']);
        $this->assertSame(60.0, $driver['timeout']);
        $this->assertSame([], $driver['client_options']);

        $retry = $empty->getRetryConfig();
        $this->assertTrue($retry['enabled']);
        $this->assertSame(3, $retry['max_attempts']);
        $this->assertSame(200, $retry['initial_delay_ms']);
        $this->assertSame(2.0, $retry['multiplier']);

        $cb = $empty->getCircuitBreakerConfig();
        $this->assertTrue($cb['enabled']);
        $this->assertSame(5, $cb['failure_threshold']);
        $this->assertSame(30.0, $cb['recovery_time']);

        $this->assertSame([], $empty->getMemoryConfig());
    }

    public function testSwarmFromConfig(): void
    {
        /** @var array<string, mixed> $published */
        $published = require __DIR__ . '/../publish/hyperf_agents.php';

        $swarm = Swarm::fromConfig($published);
        $this->assertInstanceOf(SwarmInterface::class, $swarm);
        $this->assertInstanceOf(AgentConfig::class, $swarm->getConfig());
        $this->assertSame('openai', $swarm->getConfig()->getDefaultDriver());

        // Also test passing AgentConfig instance directly
        $cfg = AgentConfig::fromArray($published);
        $swarm2 = Swarm::fromConfig($cfg);
        $this->assertSame($cfg, $swarm2->getConfig());
    }
}
