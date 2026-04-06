<?php

declare(strict_types=1);

namespace Lecabe\Invoicing\Resources;

use Lecabe\Invoicing\InvoicingClient;

/**
 * Current user profile for an invoicing API key **tied to a user** (`GET /v1/me`).
 *
 * Uses {@see InvoicingClient::sendV1} (`X-API-Key` + Bearer with the same key). The JWT from
 * {@see Auth::login} is **not** valid for this route (server rejects it as wrong credential style).
 *
 * If the configured key is the environment **global** `INVOICING_API_KEY`, or an API key with no
 * user linkage, the API returns **403** with `error.code` **USER_CONTEXT_NOT_AVAILABLE**
 * (“This API key is not tied to a user.”). Catch {@see \Lecabe\Invoicing\Exception\ApiException} and inspect `errorCode`.
 */
final class Me
{
    public function __construct(
        private readonly InvoicingClient $client,
    ) {
    }

    /**
     * GET /v1/me — `{ user: { id, email, email_verified_at, created_at } }` on success.
     *
     * @return array<string, mixed>
     */
    public function get(): array
    {
        return $this->client->sendV1('GET', '/v1/me');
    }
}
