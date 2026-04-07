<?php

declare(strict_types=1);

namespace Lecabe\Invoicing\Resources;

use Lecabe\Invoicing\InvoicingClient;

final class Projects
{
    private const LIST_KEYS = ['page', 'per_page', 'client_id'];

    public function __construct(
        private readonly InvoicingClient $client,
    ) {
    }

    /**
     * GET /v1/projects — optional client_id filter + pagination.
     *
     * @param array<string, mixed> $query page, per_page, client_id
     * @return array<string, mixed>
     */
    public function list(array $query = []): array
    {
        $q = $this->onlyKeys($query, self::LIST_KEYS);

        return $this->client->sendV1('GET', '/v1/projects', ['query' => $q]);
    }

    /**
     * @param array<string, mixed> $body client_id, name, default_hourly_rate_cents optional
     * @return array<string, mixed>
     */
    public function create(array $body): array
    {
        return $this->client->sendV1('POST', '/v1/projects', ['json' => $body]);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $id): array
    {
        return $this->client->sendV1('GET', '/v1/projects/' . rawurlencode($id));
    }

    /**
     * @param array<string, mixed> $body name and/or default_hourly_rate_cents
     * @return array<string, mixed>
     */
    public function update(string $id, array $body): array
    {
        return $this->client->sendV1('PATCH', '/v1/projects/' . rawurlencode($id), ['json' => $body]);
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(string $id): array
    {
        return $this->client->sendV1('DELETE', '/v1/projects/' . rawurlencode($id));
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
