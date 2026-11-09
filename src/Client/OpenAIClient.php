<?php

declare(strict_types=1);

namespace HyperfAgents\Client;

use GuzzleHttp\Client;
use Hyperf\Guzzle\ClientFactory;
use HyperfAgents\Exception\LlmApiException;
use Psr\Container\ContainerInterface;

class OpenAIClient
{
    protected Client $httpClient;

    public function __construct(
        protected string $apiKey = '',
        protected string $baseUrl = 'https://api.openai.com/v1',
        ?ContainerInterface $container = null,
        array $clientOptions = []
    ) {
        $this->apiKey = $this->apiKey ?: (string)getenv('OPENAI_API_KEY');

        if ($container && $container->has(ClientFactory::class)) {
            $factory = $container->get(ClientFactory::class);
            $this->httpClient = $factory->create(array_merge([
                'base_uri' => rtrim($this->baseUrl, '/') . '/',
                'timeout' => 60.0,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
            ], $clientOptions));
        } else {
            $this->httpClient = new Client(array_merge([
                'base_uri' => rtrim($this->baseUrl, '/') . '/',
                'timeout' => 60.0,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
            ], $clientOptions));
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function chatCompletions(array $payload): array
    {
        if (empty($this->apiKey)) {
            throw new LlmApiException('OpenAI API key is missing. Set OPENAI_API_KEY env or configure OpenAIClient.', 401, null, $payload);
        }

        try {
            $response = $this->httpClient->post('chat/completions', [
                'json' => $payload,
            ]);
        } catch (\Throwable $e) {
            throw new LlmApiException("OpenAI HTTP request failed: {$e->getMessage()}", (int) $e->getCode(), $e, $payload);
        }

        $body = (string) $response->getBody();
        $data = json_decode($body, true);

        if (! is_array($data)) {
            throw new LlmApiException('Invalid JSON response from OpenAI API.', 500, null, $payload);
        }

        return $data;
    }
}
