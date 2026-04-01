<?php

declare(strict_types=1);

namespace Lecabe\Invoicing\Resources;

use Lecabe\Invoicing\InvoicingClient;

final class Clients
{
    private const LIST_KEYS = ['page', 'per_page'];

    public function __construct(
        private readonly InvoicingClient $client,
    ) {
    }

    /**
     * GET /v1/clients — returns full decoded body including `data` and `meta`.
     *
     * @param array<string, mixed> $query page, per_page
     * @return array<string, mixed>
     */
    public function list(array $query = []): array
    {
        $q = $this->onlyKeys($query, self::LIST_KEYS);

        return $this->client->sendV1('GET', '/v1/clients', ['query' => $q]);
    }

    /**
     * @param array<string, mixed> $body name (required), email optional/nullable
     * @return array<string, mixed>
     */
    public function create(array $body): array
    {
        return $this->client->sendV1('POST', '/v1/clients', ['json' => $body]);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $id): array
    {
        return $this->client->sendV1('GET', '/v1/clients/' . rawurlencode($id));
    }

    /**
     * @param array<string, mixed> $body at least one of name, email
     * @return array<string, mixed>
     */
    public function update(string $id, array $body): array
    {
        return $this->client->sendV1('PATCH', '/v1/clients/' . rawurlencode($id), ['json' => $body]);
    }

    /**
     * DELETE /v1/clients/{id} — 204, empty array.
     *
     * @return array<string, mixed>
     */
    public function delete(string $id): array
    {
        return $this->client->sendV1('DELETE', '/v1/clients/' . rawurlencode($id));
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $keys
     * @return array<string, mixed>
     */
    private function onlyKeys(array $data, array $keys): array
    {
        $out = [];
        foreach ($keys as $k) {
            if (!array_key_exists($k, $data)) {
                continue;
            }
            $v = $data[$k];
            if ($v === null || $v === '') {
                continue;
            }
            $out[$k] = $v;
        }

        return $out;
    }
}
