<?php
namespace App\Services\AiProviders;

use App\Contracts\AiProviderInterface;

/**
 * OpenAI Chat Completions provider.
 */
class OpenAiProvider implements AiProviderInterface
{
    private string $apiKey;
    private string $model;
    private int $timeout;

    public function __construct(string $apiKey, string $model = 'gpt-4o-mini', int $timeout = 60)
    {
        $this->apiKey = $apiKey;
        $this->model  = $model;
        $this->timeout = $timeout;
    }

    public function name(): string
    {
        return 'openai';
    }

    public function label(): string
    {
        return 'OpenAI ' . $this->model;
    }

    public function chat(array $messages, array $options = []): string
    {
        $payload = [
            'model'    => $this->model,
            'messages' => array_values($messages),
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens'  => (int)($options['max_tokens'] ?? 3000),
        ];

        $body = json_encode($payload);
        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
        ]);
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode >= 400) {
            $detail = is_string($response) ? mb_substr($response, 0, 500) : $curlError;
            throw new \RuntimeException("OpenAI request failed (HTTP $httpCode): $detail");
        }

        $data = json_decode($response, true);
        return trim($data['choices'][0]['message']['content'] ?? '');
    }
}
