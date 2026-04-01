<?php

declare(strict_types=1);

namespace Lecabe\Invoicing;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Lecabe\Invoicing\Exception\ApiException;
use Lecabe\Invoicing\Request\RequestBuilder;
use Lecabe\Invoicing\Resources\Clients;
use Lecabe\Invoicing\Resources\InvoiceEmails;
use Lecabe\Invoicing\Resources\InvoiceLines;
use Lecabe\Invoicing\Resources\InvoicePdf;
use Lecabe\Invoicing\Resources\Invoices;
use Lecabe\Invoicing\Resources\Projects;
use Lecabe\Invoicing\Resources\TimeEntries;
use Lecabe\Invoicing\System\Health;
use Lecabe\Invoicing\System\Ready;

/**
 * HTTP client for LecabeInvoicing. Injects {@see ClientInterface} for tests (MockHandler).
 *
 * - Requests under `/v1/*` send X-API-Key and Authorization: Bearer (same key).
 * - `/health` and `/ready` are called without those headers.
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
