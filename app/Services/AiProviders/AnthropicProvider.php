<?php
namespace App\Services\AiProviders;

use App\Contracts\AiProviderInterface;

/**
 * Anthropic (Claude) Messages API provider.
 */
class AnthropicProvider implements AiProviderInterface
{
    private string $apiKey;
    private string $model;
    private int $timeout;

    public function __construct(string $apiKey, string $model = 'claude-3-5-sonnet-20241022', int $timeout = 60)
    {
        $this->apiKey  = $apiKey;
        $this->model   = $model;
        $this->timeout = $timeout;
    }

    public function name(): string
    {
        return 'anthropic';
    }

    public function label(): string
    {
        return 'Claude (' . $this->model . ')';
    }

    public function chat(array $messages, array $options = []): string
    {
        $system = '';
        $anthropicMessages = [];
        foreach ($messages as $m) {
            if ($m['role'] === 'system') {
                $system = (string)$m['content'];
            } else {
                $anthropicMessages[] = ['role' => $m['role'], 'content' => (string)$m['content']];
            }
        }

        $payload = [
            'model'      => $this->model,
            'max_tokens' => (int)($options['max_tokens'] ?? 3000),
            'temperature'=> $options['temperature'] ?? 0.7,
            'messages'   => $anthropicMessages,
        ];
        if ($system !== '') {
            $payload['system'] = $system;
        }

        $body = json_encode($payload);
        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'x-api-key: ' . $this->apiKey,
                'anthropic-version: 2023-06-01',
            ],
        ]);
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode >= 400) {
            $detail = is_string($response) ? mb_substr($response, 0, 500) : $curlError;
            throw new \RuntimeException("Anthropic request failed (HTTP $httpCode): $detail");
        }

        $data = json_decode($response, true);
        return trim($data['content'][0]['text'] ?? '');
    }
}
