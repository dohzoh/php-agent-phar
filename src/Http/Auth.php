<?php

declare(strict_types=1);

namespace Xrea\Agent\Http;

final class Auth
{
    public function __construct(
        private readonly ?string $token,
    ) {
    }

    public function isRequired(): bool
    {
        return trim((string) $this->token) !== '';
    }

    /**
     * @param array<string, mixed> $server
     */
    public function authorize(array $server): bool
    {
        if (!$this->isRequired()) {
            return true;
        }

        $provided = self::bearerTokenFromServer($server);

        if ($provided === null) {
            return false;
        }

        $configured = (string) $this->token;

        return hash_equals($configured, $provided);
    }

    /**
     * @param array<string, mixed> $server
     */
    public static function bearerTokenFromServer(array $server): ?string
    {
        $header = $server['HTTP_AUTHORIZATION'] ?? $server['REDIRECT_HTTP_AUTHORIZATION'] ?? null;

        if ($header === null || !is_string($header)) {
            return null;
        }

        // Case-insensitive Bearer prefix check
        if (stripos($header, 'bearer ') !== 0) {
            return null;
        }

        $token = substr($header, 7);

        return $token !== '' ? $token : null;
    }
}