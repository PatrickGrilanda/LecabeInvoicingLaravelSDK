<?php

declare(strict_types=1);

namespace Lecabe\Invoicing\Request;

/**
 * Helpers for building Guzzle request options (auth headers for /v1).
 */
final class RequestBuilder
{
    /**
     * Invoicing API key: {@see withV1Auth}. Use for resource clients (`sendV1`, `getV1`) and endpoints that expect `X-API-Key` + Bearer (same key).
     *
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

    /**
     * JWT-only Bearer auth. Does **not** set `X-API-Key`. Use for user/session JWT routes; pass the raw JWT string, not the invoicing API key stored in {@see \Lecabe\Invoicing\Config}.
     *
     * @param array<string, string|string[]> $headers
     * @return array<string, string|string[]>
     */
    public static function withJwtBearer(array $headers, string $jwt): array
    {
        return array_merge($headers, [
            'Authorization' => 'Bearer ' . $jwt,
        ]);
    }

    /**
     * HTTP Basic only. Does **not** set `X-API-Key`. Use for admin/account Basic routes. Encoding is ASCII `user:password` via {@see base64_encode}; non-ASCII credentials may need RFC 7617 encoding before calling this helper.
     *
     * @param array<string, string|string[]> $headers
     * @return array<string, string|string[]>
     */
    public static function withHttpBasicCredentials(array $headers, string $user, string $password): array
    {
        $token = base64_encode($user . ':' . $password);

        return array_merge($headers, [
            'Authorization' => 'Basic ' . $token,
        ]);
    }
}
