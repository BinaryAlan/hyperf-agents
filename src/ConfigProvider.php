<?php

declare(strict_types=1);

namespace HyperfAgents;

class ConfigProvider
{
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                Orchestrator\SwarmInterface::class => Orchestrator\Swarm::class,
                Tool\ConcurrentToolExecutorInterface::class => Tool\ConcurrentToolExecutor::class,
            ],
            'commands' => [],
            'annotations' => [
                'scan' => [
                    'paths' => [
                        __DIR__,
                    ],
                ],
            ],
            'publish' => [
                [
                    'id' => 'config',
                    'description' => 'The config for Hyperf Agents.',
                    'source' => __DIR__ . '/../publish/hyperf_agents.php',
                    'destination' => BASE_PATH . '/config/autoload/hyperf_agents.php',
                ],
            ],
        ];
    }
}
