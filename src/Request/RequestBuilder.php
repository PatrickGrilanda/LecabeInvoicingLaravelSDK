<?php

declare(strict_types=1);

namespace Lecabe\Invoicing\Request;

/**
 * Helpers for building Guzzle request options (auth headers for /v1).
 */
final class RequestBuilder
{
    /**
     * @param array<string, string|string[]> $headers
     * @return array<string, string|string[]>
     */
    public static function withV1Auth(array $headers, string $apiKey): array
    {
        return array_merge($headers, [
            'X-API-Key' => $apiKey,
            'Authorization' => 'Bearer ' . $apiKey,
        ]);
    }
}
