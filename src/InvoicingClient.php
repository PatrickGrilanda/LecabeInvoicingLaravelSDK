<?php

declare(strict_types=1);

namespace Lecabe\Invoicing;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Lecabe\Invoicing\Exception\ApiException;
use Lecabe\Invoicing\Request\RequestBuilder;
use Lecabe\Invoicing\Resources\AdminApiKeys;
use Lecabe\Invoicing\Resources\Auth;
use Lecabe\Invoicing\Resources\Clients;
use Lecabe\Invoicing\Resources\InvoiceEmails;
use Lecabe\Invoicing\Resources\Me;
use Lecabe\Invoicing\Resources\InvoiceLines;
use Lecabe\Invoicing\Resources\InvoicePdf;
use Lecabe\Invoicing\Resources\Invoices;
use Lecabe\Invoicing\Resources\Projects;
use Lecabe\Invoicing\Resources\PunchTimer;
use Lecabe\Invoicing\Resources\TimeEntries;
use Lecabe\Invoicing\Resources\UserMeApiKeys;
use Lecabe\Invoicing\System\Health;
use Lecabe\Invoicing\System\Ready;

/**
 * HTTP client for LecabeInvoicing. Injects {@see ClientInterface} for tests (MockHandler).
 *
 * Transport modes for `/v1/*` (pick one per request):
 * - **API key (invoicing):** {@see sendV1}, {@see getV1}, resource facades — `X-API-Key` + `Authorization: Bearer` (same key from config).
 * - **JWT:** {@see sendV1WithJwt} — Bearer(JWT) only; does not use config API key as the token.
 * - **HTTP Basic:** {@see sendV1WithBasic} — account credentials for admin-style routes.
 * - **Public:** {@see sendV1Public} — no default invoicing headers (optional caller headers only).
 *
 * **Auth resource:** {@see auth} — register/login/verify-email via {@see sendV1Public}; resend-verification via {@see sendV1WithJwt}.
 * **User API keys:** {@see userMeApiKeys} — create key via {@see sendV1WithJwt} (`POST /v1/users/me/api-keys`); JWT from login only.
 * **Admin API keys:** {@see adminApiKeys} — create key via {@see sendV1WithBasic} (`POST /v1/admin/api-keys`); account email + password.
 * **Identity:** {@see me} — user profile via {@see sendV1}; requires user-linked API key (not login JWT).
 *
 * `/health` and `/ready` are called without invoicing auth headers.
 */
final class InvoicingClient
{
    private ClientInterface $http;

    public function __construct(
        private readonly Config $config,
        ?ClientInterface $http = null,
    ) {
        $this->http = $http ?? new Client([
            'base_uri' => $this->normalizeBaseUri($config->baseUri),
            'timeout' => $config->timeout,
            'http_errors' => false,
        ]);
    }

    public function config(): Config
    {
        return $this->config;
    }

    public function httpClient(): ClientInterface
    {
        return $this->http;
    }

    /**
     * GET /health (no /v1, no API key headers).
     *
     * @return array<string, mixed>
     */
    public function health(): array
    {
        return (new Health($this))->get();
    }

    /**
     * GET /ready (no /v1, no API key headers).
     *
     * @return array<string, mixed>
     */
    public function ready(): array
    {
        return (new Ready($this))->get();
    }

    /**
     * Auth endpoints: public register/login/verify-email; JWT-only resend-verification.
     */
    public function auth(): Auth
    {
        return new Auth($this);
    }

    /**
     * GET /v1/me — invoicing API key tied to a user. Login JWT is not accepted. See {@see Me} PHPDoc for `USER_CONTEXT_NOT_AVAILABLE`.
     */
    public function me(): Me
    {
        return new Me($this);
    }

    /**
     * POST /v1/users/me/api-keys — JWT (login) only; verified email required on server. See {@see UserMeApiKeys}.
     */
    public function userMeApiKeys(): UserMeApiKeys
    {
        return new UserMeApiKeys($this);
    }

    /**
     * POST /v1/admin/api-keys — HTTP Basic (account credentials); no setup token. See {@see AdminApiKeys}.
     */
    public function adminApiKeys(): AdminApiKeys
    {
        return new AdminApiKeys($this);
    }

    /**
     * GET a /v1 path with auth headers; decodes JSON on 2xx; throws {@see ApiException} on 4xx/5xx.
     *
     * @return array<string, mixed>
     */
    public function getV1(string $path, array $options = []): array
    {
        return $this->sendJsonWithV1Auth('GET', $path, $options);
    }

    public function clients(): Clients
    {
        return new Clients($this);
    }

    public function projects(): Projects
    {
        return new Projects($this);
    }

    public function timeEntries(): TimeEntries
    {
        return new TimeEntries($this);
    }

    /**
     * Cronómetro tipo ponto — API **0.7.1+** (`/v1/punch-timer/*`). Requer cabeçalho `X-Punch-Actor-Id` em todos os pedidos.
     */
    public function punchTimer(): PunchTimer
    {
        return new PunchTimer($this);
    }

    public function invoices(): Invoices
    {
        return new Invoices($this);
    }

    public function invoiceLines(): InvoiceLines
    {
        return new InvoiceLines($this);
    }

    public function invoicePdf(): InvoicePdf
    {
        return new InvoicePdf($this);
    }

    public function invoiceEmails(): InvoiceEmails
    {
        return new InvoiceEmails($this);
    }

    /**
     * Authenticated /v1 request. Handles 204 No Content as empty array.
     *
     * @param array<string, mixed> $options Guzzle options (query, json, headers, …)
     * @return array<string, mixed>
     */
    public function sendV1(string $method, string $path, array $options = []): array
    {
        $options['headers'] = RequestBuilder::withV1Auth(
            $options['headers'] ?? [],
            $this->config->apiKey,
        );

        return $this->sendV1Json($method, $path, $options);
    }

    /**
     * `/v1` request with Bearer(JWT) only — no `X-API-Key`, never uses {@see Config::apiKey} as the JWT.
     *
     * @param array<string, mixed> $options Guzzle options (query, json, headers, …)
     * @return array<string, mixed>
     */
    public function sendV1WithJwt(string $method, string $path, string $jwt, array $options = []): array
    {
        $options['headers'] = RequestBuilder::withJwtBearer($options['headers'] ?? [], $jwt);

        return $this->sendV1Json($method, $path, $options);
    }

    /**
     * `/v1` request with HTTP Basic only — no `X-API-Key`.
     *
     * @param array<string, mixed> $options Guzzle options (query, json, headers, …)
     * @return array<string, mixed>
     */
    public function sendV1WithBasic(string $method, string $path, string $user, string $password, array $options = []): array
    {
        $options['headers'] = RequestBuilder::withHttpBasicCredentials(
            $options['headers'] ?? [],
            $user,
            $password,
        );

        return $this->sendV1Json($method, $path, $options);
    }

    /**
     * `/v1` request without default invoicing auth. Same JSON/204/error handling as {@see sendV1}; add headers in `$options['headers']` only if needed.
     *
     * @param array<string, mixed> $options Guzzle options (query, json, headers, …)
     * @return array<string, mixed>
     */
    public function sendV1Public(string $method, string $path, array $options = []): array
    {
        return $this->sendV1Json($method, $path, $options);
    }

    /**
     * @return array<string, mixed>
     */
    public function systemGet(string $path, array $options = []): array
    {
        return $this->sendJson('GET', $path, $options);
    }

    /**
     * @param array<string, mixed> $options Guzzle request options
     * @return array<string, mixed>
     */
    private function sendJsonWithV1Auth(string $method, string $path, array $options = []): array
    {
        $options['headers'] = RequestBuilder::withV1Auth(
            $options['headers'] ?? [],
            $this->config->apiKey,
        );

        return $this->sendJson($method, $path, $options);
    }

    /**
     * Shared /v1 response handling for {@see sendV1} and alternate auth transports (204, JSON decode).
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function sendV1Json(string $method, string $path, array $options = []): array
    {
        $response = $this->http->request($method, $path, $options);
        if ($response->getStatusCode() >= 400) {
            throw ApiException::fromResponse($response);
        }

        if ($response->getStatusCode() === 204) {
            return [];
        }

        $body = (string) $response->getBody();
        if ($body === '') {
            return [];
        }

        $decoded = json_decode($body, true);

        return \is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    private function sendJson(string $method, string $path, array $options = []): array
    {
        $response = $this->http->request($method, $path, $options);
        if ($response->getStatusCode() >= 400) {
            throw ApiException::fromResponse($response);
        }

        $body = (string) $response->getBody();
        if ($body === '') {
            return [];
        }

        $decoded = json_decode($body, true);

        return \is_array($decoded) ? $decoded : [];
    }

    private function normalizeBaseUri(string $uri): string
    {
        return rtrim($uri, '/') . '/';
    }
}
