<?php

declare(strict_types=1);

namespace Lecabe\Invoicing\Resources;

use Lecabe\Invoicing\InvoicingClient;

/**
 * POST /v1/invoices/{id}/emails — body matches API {@see sendInvoiceEmailBodySchema}:
 * `to` (required), optional `subject`, `attach_pdf`, `fiscal_attachment` with `filename` + `content_base64` only.
 */
final class InvoiceEmails
{
    public function __construct(
        private readonly InvoicingClient $client,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function send(string $id, array $payload): array
    {
        $body = $this->filterPayload($payload);
        if (!isset($body['to']) || $body['to'] === '' || !\is_string($body['to'])) {
            throw new \InvalidArgumentException('to (email string) is required');
        }

        return $this->client->sendV1(
            'POST',
            '/v1/invoices/' . rawurlencode($id) . '/emails',
            ['json' => $body],
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function filterPayload(array $payload): array
    {
        $out = [];
        if (array_key_exists('to', $payload)) {
            $out['to'] = $payload['to'];
        }
        if (array_key_exists('subject', $payload) && $payload['subject'] !== null && $payload['subject'] !== '') {
            $out['subject'] = $payload['subject'];
        }
        if (array_key_exists('attach_pdf', $payload)) {
            $out['attach_pdf'] = (bool) $payload['attach_pdf'];
        }
        if (isset($payload['fiscal_attachment']) && \is_array($payload['fiscal_attachment'])) {
            $fa = $payload['fiscal_attachment'];
            $slice = [];
            if (isset($fa['filename'])) {
                $slice['filename'] = $fa['filename'];
            }
            if (isset($fa['content_base64'])) {
                $slice['content_base64'] = $fa['content_base64'];
            }
            if ($slice !== []) {
                $out['fiscal_attachment'] = $slice;
            }
        }

        return $out;
    }
}
