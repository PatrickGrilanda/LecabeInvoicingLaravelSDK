<?php

declare(strict_types=1);

namespace Lecabe\Invoicing\Resources;

use Lecabe\Invoicing\InvoicingClient;

/**
 * Invoice line items under /v1/invoices/{id}/lines and POST .../recalculate.
 *
 * There is no dedicated GET /lines collection; {@see list} loads the invoice and returns embedded `lines`.
 */
final class InvoiceLines
{
    public function __construct(
        private readonly InvoicingClient $client,
    ) {
    }

    /**
     * Lines embedded on GET /v1/invoices/{id}.
     *
     * @return list<array<string, mixed>>
     */
    public function list(string $invoiceId): array
    {
        $invoice = $this->client->sendV1('GET', '/v1/invoices/' . rawurlencode($invoiceId));
        $lines = $invoice['lines'] ?? [];

        return \is_array($lines) ? $lines : [];
    }

    /**
     * @param array<string, mixed> $body description, quantity, unit_price_cents
     * @return array<string, mixed>
     */
    public function create(string $invoiceId, array $body): array
    {
        $path = '/v1/invoices/' . rawurlencode($invoiceId) . '/lines';

        return $this->client->sendV1('POST', $path, ['json' => $body]);
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function update(string $invoiceId, string $lineId, array $body): array
    {
        $path = '/v1/invoices/' . rawurlencode($invoiceId) . '/lines/' . rawurlencode($lineId);

        return $this->client->sendV1('PATCH', $path, ['json' => $body]);
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(string $invoiceId, string $lineId): array
    {
        $path = '/v1/invoices/' . rawurlencode($invoiceId) . '/lines/' . rawurlencode($lineId);

        return $this->client->sendV1('DELETE', $path);
    }

    /**
     * POST /v1/invoices/{id}/recalculate — no body; returns full invoice.
     *
     * @return array<string, mixed>
     */
    public function recalculate(string $invoiceId): array
    {
        $path = '/v1/invoices/' . rawurlencode($invoiceId) . '/recalculate';

        return $this->client->sendV1('POST', $path);
    }
}
