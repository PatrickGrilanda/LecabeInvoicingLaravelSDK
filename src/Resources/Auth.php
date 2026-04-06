<?php

declare(strict_types=1);

namespace Lecabe\Invoicing\Resources;

use Lecabe\Invoicing\InvoicingClient;

/**
 * Public registration/login/email verification and JWT-only resend-verification.
 *
 * {@see register}, {@see login}, and {@see verifyEmail} use {@see InvoicingClient::sendV1Public} (no `X-API-Key`).
 * {@see resendVerification} uses {@see InvoicingClient::sendV1WithJwt} (Bearer JWT only).
 */
final class Auth
{
    public function __construct(
        private readonly InvoicingClient $client,
    ) {
    }

    /**
     * POST /v1/auth/register — body must include `email` and `password` (server: min 8 chars).
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed> e.g. `{ user: ... }` on 201
     */
    public function register(array $body): array
    {
        return $this->client->sendV1Public('POST', '/v1/auth/register', ['json' => $body]);
    }

    /**
     * POST /v1/auth/login — `{ access_token, token_type, expires_in }` on success.
     *
     * @param array<string, mixed> $body `email`, `password`
     * @return array<string, mixed>
     */
    public function login(array $body): array
    {
        return $this->client->sendV1Public('POST', '/v1/auth/login', ['json' => $body]);
    }

    /**
     * GET /v1/auth/verify-email?token=
     *
     * @return array<string, mixed> e.g. `{ verified: true, email: string }`
     */
    public function verifyEmail(string $token): array
    {
        return $this->client->sendV1Public('GET', '/v1/auth/verify-email', [
            'query' => ['token' => $token],
        ]);
    }

    /**
     * POST /v1/auth/resend-verification — JWT from {@see login} required; no invoicing API key headers.
     *
     * @return array<string, mixed> e.g. `{ sent: true }`
     */
    public function resendVerification(string $jwt): array
    {
        return $this->client->sendV1WithJwt('POST', '/v1/auth/resend-verification', $jwt);
    }
}
