<?php

declare(strict_types=1);

namespace Lecabe\Invoicing\System;

use Lecabe\Invoicing\InvoicingClient;

/**
 * System readiness: GET /ready.
 */
final class Ready
{
    public function __construct(
        private readonly InvoicingClient $client,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function get(): array
    {
        return $this->client->systemGet('/ready');
    }
}
