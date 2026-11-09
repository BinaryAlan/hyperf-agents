<?php

declare(strict_types=1);

namespace HyperfAgents\Tests;

use HyperfAgents\Agent\Agent;
use HyperfAgents\Agent\Handoff;
use HyperfAgents\Tool\ToolInterface;
use PHPUnit\Framework\TestCase;

class AgentTest extends TestCase
{
    public function testAgentInitialization(): void
    {
        $agent = new Agent(
            name: 'CustomerService',
            instructions: 'You are a helpful customer service agent.',
            model: 'gpt-4o'
        );

        $this->assertSame('CustomerService', $agent->getName());
        $this->assertSame('You are a helpful customer service agent.', $agent->getInstructions());
        $this->assertSame('gpt-4o', $agent->getModel());
        $this->assertEmpty($agent->getTools());

        $array = $agent->toArray();
        $this->assertSame('CustomerService', $array['name']);
        $this->assertSame('gpt-4o', $array['model']);
        $this->assertSame('You are a helpful customer service agent.', $array['instructions']);
        $this->assertSame(0, $array['tools_count']);
    }

    public function testAddCallableTool(): void
    {
        $agent = new Agent('TestAgent', 'instructions');

        $callable = fn (array $args) => $args['x'] + 1;
        $agent->addTool(
            name: 'increment',
            tool: $callable,
            description: 'Increments input by 1',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'x' => ['type' => 'integer'],
                ],
                'required' => ['x'],
            ]
        );

        $tools = $agent->getTools();
        $this->assertArrayHasKey('increment', $tools);
        $this->assertSame($callable, $tools['increment']['handler']);
        $this->assertSame('Increments input by 1', $tools['increment']['description']);
        $this->assertSame(['type' => 'integer'], $tools['increment']['parameters']['properties']['x']);
    }

    public function testAddToolInterfaceInstance(): void
    {
        $toolMock = new class implements ToolInterface {
            public function getName(): string
            {
                return 'weather_lookup';
            }

            public function getDescription(): string
            {
                return 'Lookup weather by city';
            }

            public function getParameters(): array
            {
                return [
                    'type' => 'object',
                    'properties' => ['city' => ['type' => 'string']],
                ];
            }

            public function execute(array $arguments, array $contextVariables = []): mixed
            {
                return ['weather' => 'sunny'];
            }
        };

        $agent = new Agent('WeatherAgent', 'checks weather', 'gpt-4o', [
            'weather' => $toolMock,
        ]);

        $tools = $agent->getTools();
        $this->assertArrayHasKey('weather_lookup', $tools);
        $this->assertSame('Lookup weather by city', $tools['weather_lookup']['description']);
        $this->assertSame(1, $agent->toArray()['tools_count']);
    }

    public function testHandoffEncapsulation(): void
    {
        $target = new Agent('BillingSpecialist', 'handles payments');
        $handoff = new Handoff(
            targetAgent: $target,
            contextVariables: ['customer_tier' => 'VIP', 'account_id' => 12345],
            handoffMessage: 'Transferring VIP user to billing specialist.'
        );

        $this->assertSame($target, $handoff->getTargetAgent());
        $this->assertSame(['customer_tier' => 'VIP', 'account_id' => 12345], $handoff->getContextVariables());
        $this->assertSame('Transferring VIP user to billing specialist.', $handoff->getHandoffMessage());
    }
}
