<?php

declare(strict_types=1);

namespace Lecabe\Invoicing\Resources;

use Lecabe\Invoicing\InvoicingClient;

/**
 * Create user-scoped invoicing API keys via JWT (login session), not the invoicing API key.
 *
 * {@see create} uses {@see InvoicingClient::sendV1WithJwt} only — Bearer JWT, no `X-API-Key`.
 * Pass the **access token** from {@see Auth::login}; do not pass {@see \Lecabe\Invoicing\Config::apiKey}.
 *
 * **Precondition:** the account email must be verified (`GET /v1/auth/verify-email?token=...`) before the
 * server accepts key creation (see `src/routes/user-api-keys.ts`).
 *
 * Typical `error.code` values from the API JSON envelope:
 * - `EMAIL_NOT_VERIFIED` (403) — complete verify-email first
 * - `UNAUTHORIZED` (401) — invalid or missing JWT subject
 * - `BAD_REQUEST` (400) — invalid body / `expires_at` rules
 * - `DATABASE_NOT_READY` (503) — schema/migrations missing (same pattern as other `/v1` resources)
 */
final class UserMeApiKeys
{
    public function __construct(
        private readonly InvoicingClient $client,
    ) {
    }

    /**
     * POST /v1/users/me/api-keys — optional body `label`, `expires_at` (ISO 8601 or null for no expiry).
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed> e.g. `{ id, api_key, label, expires_at }` on 201
     */
    public function create(string $jwt, array $body = []): array
    {
        $options = $body === [] ? [] : ['json' => $body];

        return $this->client->sendV1WithJwt('POST', '/v1/users/me/api-keys', $jwt, $options);
    }
}
