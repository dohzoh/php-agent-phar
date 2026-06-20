<?php

namespace Xrea\Agent\AI;

class Client
{
    private string $url;
    private string $apiKey;
    private string $model;
    private int $timeout;

    public function __construct(string $url, string $apiKey, string $model, int $timeout = 60)
    {
        $this->url = rtrim($url, '/');
        $this->apiKey = $apiKey;
        $this->model = $model;
        $this->timeout = $timeout;
    }

    public static function fromConfig(array $config): self
    {
        return new self(
            $config['url'] ?? '',
            $config['api_key'] ?? '',
            $config['model'] ?? 'default',
        );
    }

    public function chat(array $messages, array $options = []): array
    {
        $body = array_merge([
            'model' => $this->model,
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => 4096,
        ], $options);

        $response = $this->request('POST', $this->url, $body);

        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Failed to parse AI response: ' . json_last_error_msg());
        }

        return $decoded;
    }

    public function chatSimple(string $prompt, array $options = []): string
    {
        $result = $this->chat([
            ['role' => 'user', 'content' => $prompt],
        ], $options);

        return $result['choices'][0]['message']['content'] ?? '';
    }

    private function request(string $method, string $url, ?array $body = null): string
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
                'User-Agent: php-agent/1.0',
            ],
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
            }
        }

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $response === '') {
            throw new \RuntimeException("AI request failed: $error");
        }

        if ($httpCode >= 400) {
            throw new \RuntimeException("AI request failed (HTTP $httpCode): $response");
        }

        return $response;
    }
}
