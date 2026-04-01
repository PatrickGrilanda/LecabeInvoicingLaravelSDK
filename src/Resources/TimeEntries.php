<?php

declare(strict_types=1);

namespace Lecabe\Invoicing\Resources;

use Lecabe\Invoicing\InvoicingClient;

final class TimeEntries
{
    private const LIST_KEYS = [
        'page',
        'per_page',
        'project_id',
        'client_id',
        'from',
        'to',
        'billable',
        'unbilled_only',
    ];

    public function __construct(
        private readonly InvoicingClient $client,
    ) {
    }

    /**
     * GET /v1/time-entries — filters + pagination; `from` / `to` are ISO datetimes.
     *
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function list(array $query = []): array
    {
        $q = $this->onlyListKeys($query);

        return $this->client->sendV1('GET', '/v1/time-entries', ['query' => $q]);
    }

    /**
     * POST /v1/time-entries — body uses `occurred_at` per API (not legacy start timestamps).
     *
     * @param array<string, mixed> $body project_id, occurred_at, duration_minutes, …
     * @return array<string, mixed>
     */
    public function create(array $body): array
    {
        return $this->client->sendV1('POST', '/v1/time-entries', ['json' => $body]);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $id): array
    {
        return $this->client->sendV1('GET', '/v1/time-entries/' . rawurlencode($id));
    }

    /**
     * PATCH /v1/time-entries/{id} — subset of fields per OpenAPI; use `occurred_at` for datetime changes.
     *
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function update(string $id, array $body): array
    {
        return $this->client->sendV1('PATCH', '/v1/time-entries/' . rawurlencode($id), ['json' => $body]);
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(string $id): array
    {
        return $this->client->sendV1('DELETE', '/v1/time-entries/' . rawurlencode($id));
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function onlyListKeys(array $data): array
    {
        $out = [];
        foreach (self::LIST_KEYS as $k) {
            if (!array_key_exists($k, $data)) {
                continue;
            }
            $v = $data[$k];
            if ($v === null || $v === '') {
                continue;
            }
            if (\is_bool($v)) {
                $out[$k] = $v ? 'true' : 'false';

                continue;
            }
            $out[$k] = $v;
        }

        return $out;
    }
}
