<?php

declare(strict_types=1);

namespace HyperfAgents\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use Hyperf\Coroutine\Coroutine;
use Hyperf\Guzzle\ClientFactory;
use HyperfAgents\Exception\CircuitBreakerOpenException;
use HyperfAgents\Exception\LlmApiException;
use HyperfAgents\Resilience\CircuitBreaker;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Throwable;

class OpenAIClient implements OpenAIClientInterface
{
    protected Client $httpClient;

    /**
     * @var (callable(int): mixed)|null
     */
    protected $sleeper;

    /**
     * @param array<string, mixed> $clientOptions
     */
    public function __construct(
        protected string $apiKey = '',
        protected string $baseUrl = 'https://api.openai.com/v1',
        ?ContainerInterface $container = null,
        array $clientOptions = [],
        protected int $maxAttempts = 3,
        protected int $initialDelayMs = 200,
        protected float $multiplier = 2.0,
        ?callable $sleeper = null,
        protected ?CircuitBreaker $circuitBreaker = null
    ) {
        $this->apiKey = $this->apiKey ?: (string) getenv('OPENAI_API_KEY');
        $this->sleeper = $sleeper;

        $defaultOptions = [
            'base_uri' => rtrim($this->baseUrl, '/') . '/',
            'timeout' => 60.0,
            'http_errors' => false,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
        ];

        $mergedOptions = array_merge($defaultOptions, $clientOptions);

        if ($container && $container->has(ClientFactory::class)) {
            $factory = $container->get(ClientFactory::class);
            $this->httpClient = $factory->create($mergedOptions);
        } else {
            $this->httpClient = new Client($mergedOptions);
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     * @throws LlmApiException
     * @throws CircuitBreakerOpenException
     */
    public function chatCompletions(array $payload): array
    {
        if (empty($this->apiKey)) {
            throw new LlmApiException('OpenAI API key is missing. Set OPENAI_API_KEY env or configure OpenAIClient.', 401, null, $payload);
        }

        if ($this->circuitBreaker !== null) {
            return $this->circuitBreaker->call(fn (): array => $this->doRequest($payload));
        }

        return $this->doRequest($payload);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     * @throws LlmApiException
     */
    protected function doRequest(array $payload): array
    {
        $maxAttempts = max(1, $this->maxAttempts);
        $lastStatus = 0;
        $lastException = null;

        for ($attempt = 1; $attempt <= $maxAttempts; ++$attempt) {
            $response = null;
            $caughtException = null;

            try {
                $response = $this->httpClient->post('chat/completions', [
                    'json' => $payload,
                ]);
            } catch (Throwable $e) {
                $caughtException = $e;
                if (method_exists($e, 'getResponse') && $e->getResponse() instanceof ResponseInterface) {
                    $response = $e->getResponse();
                }
            }

            if ($response instanceof ResponseInterface) {
                $statusCode = $response->getStatusCode();

                // 2xx Success
                if ($statusCode >= 200 && $statusCode < 300) {
                    $body = (string) $response->getBody();
                    $data = json_decode($body, true);

                    if (! is_array($data)) {
                        throw new LlmApiException('Invalid JSON response from OpenAI API.', 500, null, $payload);
                    }

                    return $data;
                }

                // HTTP 429 or >= 500: TRANSIENT -> schedule retry
                if ($statusCode === 429 || $statusCode >= 500) {
                    $lastStatus = $statusCode;
                    $lastException = $caughtException ?? new LlmApiException("OpenAI HTTP request failed with status {$statusCode}.", $statusCode, null, $payload);

                    if ($attempt < $maxAttempts) {
                        $this->delay($attempt);
                        continue;
                    }

                    break;
                }

                // Any other 4xx: NON-TRANSIENT -> immediately throw
                if ($statusCode >= 400) {
                    $message = $caughtException ? "OpenAI HTTP request failed: {$caughtException->getMessage()}" : "OpenAI HTTP request failed with status {$statusCode}.";
                    throw new LlmApiException($message, $statusCode, $caughtException, $payload);
                }

                // Unexpected status code (< 200 or 3xx)
                $message = $caughtException ? "OpenAI HTTP request failed: {$caughtException->getMessage()}" : "OpenAI HTTP request failed with status {$statusCode}.";
                throw new LlmApiException($message, $statusCode, $caughtException, $payload);
            }

            // No response: check if TransferException (connection / network error)
            if ($caughtException instanceof TransferException) {
                $lastStatus = (int) $caughtException->getCode();
                $lastException = $caughtException;

                if ($attempt < $maxAttempts) {
                    $this->delay($attempt);
                    continue;
                }

                break;
            }

            // Any other unknown exception
            if ($caughtException !== null) {
                throw new LlmApiException("OpenAI HTTP request failed: {$caughtException->getMessage()}", (int) $caughtException->getCode(), $caughtException, $payload);
            }
        }

        $message = $lastException !== null
            ? "OpenAI HTTP request failed after {$maxAttempts} attempts: {$lastException->getMessage()}"
            : "OpenAI HTTP request failed after {$maxAttempts} attempts with status {$lastStatus}.";

        throw new LlmApiException($message, $lastStatus, $lastException, $payload);
    }

    protected function delay(int $attempt): void
    {
        $delayMs = (int) round($this->initialDelayMs * ($this->multiplier ** ($attempt - 1)));

        if ($this->sleeper !== null) {
            ($this->sleeper)($delayMs);
        } elseif ($delayMs > 0) {
            if (Coroutine::inCoroutine()) {
                Coroutine::sleep($delayMs / 1000);
            } else {
                usleep($delayMs * 1000);
            }
        }
    }
}
