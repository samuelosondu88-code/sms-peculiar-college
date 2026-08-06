<?php
namespace App\Services\AiProviders;

use App\Contracts\AiProviderInterface;

/**
 * Google Gemini (Generative Language API) provider.
 */
class GeminiProvider implements AiProviderInterface
{
    private string $apiKey;
    private string $model;
    private int $timeout;

    public function __construct(string $apiKey, string $model = 'gemini-1.5-pro', int $timeout = 60)
    {
        $this->apiKey  = $apiKey;
        $this->model   = $model;
        $this->timeout = $timeout;
    }

    public function name(): string
    {
        return 'gemini';
    }

    public function label(): string
    {
        return 'Gemini (' . $this->model . ')';
    }

    public function chat(array $messages, array $options = []): string
    {
        $contents = [];
        foreach ($messages as $m) {
            if ($m['role'] === 'system') {
                // Gemini has no system role; prepend to the first user message.
                if (!isset($contents[0])) {
                    $contents[0] = ['role' => 'user', 'parts' => [['text' => '']]];
                }
                $contents[0]['parts'][0]['text'] = $m['content'] . "\n\n---\n\n" . $contents[0]['parts'][0]['text'];
                continue;
            }
            $contents[] = [
                'role'  => $m['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => (string)$m['content']]],
            ];
        }

        $payload = [
            'contents'         => $contents,
            'generationConfig' => [
                'temperature'   => $options['temperature'] ?? 0.7,
                'maxOutputTokens' => (int)($options['max_tokens'] ?? 3000),
            ],
        ];

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'
             . rawurlencode($this->model) . ':generateContent?key=' . rawurlencode($this->apiKey);

        $body = json_encode($payload);
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        ]);
        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $httpCode >= 400) {
            $detail = is_string($response) ? mb_substr($response, 0, 500) : $curlError;
            throw new \RuntimeException("Gemini request failed (HTTP $httpCode): $detail");
        }

        $data = json_decode($response, true);
        return trim($data['candidates'][0]['content']['parts'][0]['text'] ?? '');
    }
}
