<?php

declare(strict_types=1);

namespace Lecabe\Invoicing\Resources;

use Lecabe\Invoicing\InvoicingClient;

/**
 * /v1/invoices — CRUD, paginated list, and draft from billable time.
 *
 * **List query (allowlist only):** `page`, `per_page`, `status` (draft|issued|void), `issue_from`, `issue_to` (YYYY-MM-DD). No client/project filters in this API version.
 *
 * **createFromTime — modes:**
 * - **By project:** set `project_id` (uuid). Optional `client_id` may be inferred by the API.
 * - **By client (aggregate):** set `client_id`; optional `project_ids` to narrow projects.
 * - **Selection:** non-empty `time_entry_ids` **or** both `period_from` and `period_to` (ISO 8601) when ids omitted — see API Zod rules.
 */
final class Invoices
{
    private const LIST_KEYS = ['page', 'per_page', 'status', 'issue_from', 'issue_to'];

    public function __construct(
        private readonly InvoicingClient $client,
    ) {
    }

    /**
     * GET /v1/invoices — full body `{ data, meta }`.
     *
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function list(array $query = []): array
    {
        $q = $this->buildListQuery($query);

        return $this->client->sendV1('GET', '/v1/invoices', ['query' => $q]);
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private function buildListQuery(array $query): array
    {
        $out = [];
        foreach (self::LIST_KEYS as $k) {
            if (!array_key_exists($k, $query)) {
                continue;
            }
            $v = $query[$k];
            if ($v === null || $v === '') {
                continue;
            }
            $out[$k] = $v;
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $body client_name, issue_date, …
     * @return array<string, mixed>
     */
    public function create(array $body): array
    {
        return $this->client->sendV1('POST', '/v1/invoices', ['json' => $body]);
    }

    /**
     * POST /v1/invoices/from-time — snake_case body per API.
     *
     * @param array<string, mixed> $payload client_id?, project_id?, period_from?, period_to?, time_entry_ids?, project_ids?
     * @return array<string, mixed>
     */
    public function createFromTime(array $payload): array
    {
        return $this->client->sendV1('POST', '/v1/invoices/from-time', ['json' => $payload]);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $id): array
    {
        return $this->client->sendV1('GET', '/v1/invoices/' . rawurlencode($id));
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function update(string $id, array $body): array
    {
        return $this->client->sendV1('PATCH', '/v1/invoices/' . rawurlencode($id), ['json' => $body]);
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(string $id): array
    {
        return $this->client->sendV1('DELETE', '/v1/invoices/' . rawurlencode($id));
    }
}
