<?php

declare(strict_types=1);

namespace HyperfAgents\Tests;

use HyperfAgents\Client\OpenAIClient;
use HyperfAgents\Exception\LlmApiException;
use PHPUnit\Framework\TestCase;

class OpenAIClientTest extends TestCase
{
    public function testMissingApiKeyThrowsLlmApiException(): void
    {
        putenv('OPENAI_API_KEY=');
        $client = new OpenAIClient(apiKey: '');

        $this->expectException(LlmApiException::class);
        $this->expectExceptionMessage('OpenAI API key is missing');

        $client->chatCompletions(['model' => 'gpt-4o']);
    }
}
