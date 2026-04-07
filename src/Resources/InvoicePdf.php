<?php

declare(strict_types=1);

namespace Lecabe\Invoicing\Resources;

use Lecabe\Invoicing\Exception\ApiException;
use Lecabe\Invoicing\InvoicingClient;
use Lecabe\Invoicing\Request\RequestBuilder;

/**
 * GET /v1/invoices/{id}/pdf — raw PDF bytes (not JSON).
 */
final class InvoicePdf
{
    public function __construct(
        private readonly InvoicingClient $client,
    ) {
    }

    /**
     * Download invoice PDF as a binary string.
     */
    public function download(string $id): string
    {
        $headers = RequestBuilder::withV1Auth(
            ['Accept' => 'application/pdf'],
            $this->client->config()->apiKey,
        );
        $response = $this->client->httpClient()->request(
            'GET',
            '/v1/invoices/' . rawurlencode($id) . '/pdf',
            ['headers' => $headers, 'http_errors' => false],
        );
        if ($response->getStatusCode() >= 400) {
            throw ApiException::fromResponse($response);
        }

        return (string) $response->getBody();
    }
}
