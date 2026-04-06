<?php

declare(strict_types=1);

namespace Lecabe\Invoicing\Resources;

use Lecabe\Invoicing\InvoicingClient;

/**
 * Create invoicing API keys via admin route using HTTP Basic (account email + password).
 *
 * {@see create} uses {@see InvoicingClient::sendV1WithBasic} only — `Authorization: Basic …`, no `X-API-Key`
 * and no JWT. There is **no** legacy setup token on this path (see `src/routes/admin-api-keys.ts`).
 *
 * **Precondition:** the account’s email must be verified before the server accepts key creation.
 *
 * Typical `error.code` values:
 * - `UNAUTHORIZED` (401) — missing/invalid Basic or wrong credentials
 * - `EMAIL_NOT_VERIFIED` (403)
 * - `BAD_REQUEST` (400) — body / `expires_at` validation
 * - `DATABASE_NOT_READY` (503) — schema/migrations missing
 */
final class AdminApiKeys
{
    public function __construct(
        private readonly InvoicingClient $client,
    ) {
    }

    /**
     * POST /v1/admin/api-keys — optional body `label`, `expires_at` (ISO 8601 or null for no expiry).
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed> e.g. `{ id, api_key, label, expires_at }` on 201
     */
    public function create(string $user, string $password, array $body = []): array
    {
        $options = $body === [] ? [] : ['json' => $body];

        return $this->client->sendV1WithBasic('POST', '/v1/admin/api-keys', $user, $password, $options);
    }
}
