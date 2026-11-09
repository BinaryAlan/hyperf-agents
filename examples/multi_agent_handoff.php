<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use HyperfAgents\Agent\Agent;
use HyperfAgents\Agent\Handoff;
use HyperfAgents\Orchestrator\Swarm;

// 1. Triage Agent (Router)
$triageAgent = new Agent(
    name: 'TriageAgent',
    instructions: 'You are a customer service triage agent. If user asks for billing or refunds, hand off to BillingAgent. If tech support, hand off to TechAgent.',
    model: 'gpt-4o'
);

// 2. Billing Agent
$billingAgent = new Agent(
    name: 'BillingAgent',
    instructions: 'You handle billing queries, invoices, and refunds. Be polite and concise.',
    model: 'gpt-4o'
);

$billingAgent->addTool(
    name: 'process_refund',
    tool: function (array $args) {
        $orderId = $args['order_id'] ?? 'unknown';
        return ['status' => 'success', 'refund_id' => 'ref_' . uniqid(), 'order_id' => $orderId];
    },
    description: 'Process a refund for a given order id',
    parameters: [
        'type' => 'object',
        'properties' => [
            'order_id' => ['type' => 'string', 'description' => 'Order ID to refund'],
        ],
        'required' => ['order_id'],
    ]
);

// Add handoff from Triage to Billing via ergonomic helper
$triageAgent->addHandoff(
    targetAgent: $billingAgent,
    description: 'Transfer conversation to BillingAgent for payments and refund issues.'
);

// 3. Run the Swarm Loop
$swarm = new Swarm();
$result = $swarm->run(
    agent: $triageAgent,
    messages: [
        ['role' => 'user', 'content' => 'I would like a refund for my order #10829.'],
    ]
);

echo 'Final Agent: ' . $result->lastAgent->getName() . PHP_EOL;
echo 'Response: ' . $result->getContent() . PHP_EOL;
echo 'Completed in ' . $result->turns . ' turns (' . round($result->totalLatencyMs, 2) . ' ms)' . PHP_EOL;
