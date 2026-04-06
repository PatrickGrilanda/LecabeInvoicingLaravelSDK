<?php

declare(strict_types=1);

namespace Lecabe\Invoicing\Resources;

use Lecabe\Invoicing\InvoicingClient;

/**
 * Punch timer — {@see https://github.com/PatrickGrilanda/LecabeInvoicing} API **0.7.1+** (`/v1/punch-timer/*`).
 *
 * Every request sends **`X-Punch-Actor-Id`** from `$actorId` (whitespace trimmed in the header value; empty after trim is still sent — the API responds **422**).
 * **`X-Civil-Timezone`** is sent only when `$civilTimezone` is non-null and non-empty; IANA validation is performed by the server.
 * Non-2xx responses (e.g. **422** validation, **409** conflicts) surface as {@see \Lecabe\Invoicing\Exception\ApiException}.
 *
 * Responses follow API snake_case: `timezone`, `local_date`, `actor_id`, `project_id`, `status` (`idle`|`running`|`paused`),
 * `worked_ms`, `segment_started_at`, `updated_at`, etc.
 */
final class PunchTimer
{
    public function __construct(
        private readonly InvoicingClient $client,
    ) {
    }

    /**
     * GET /v1/punch-timer/status?project_id=
     *
     * @return array<string, mixed>
     */
    public function status(string $actorId, string $projectId, ?string $civilTimezone = null): array
    {
        return $this->client->sendV1('GET', '/v1/punch-timer/status', [
            'query' => ['project_id' => $projectId],
            'headers' => $this->punchHeaders($actorId, $civilTimezone),
        ]);
    }

    /**
     * POST /v1/punch-timer/play — body `{ "project_id": "..." }`.
     *
     * @return array<string, mixed>
     */
    public function play(string $actorId, string $projectId, ?string $civilTimezone = null): array
    {
        return $this->client->sendV1('POST', '/v1/punch-timer/play', [
            'json' => ['project_id' => $projectId],
            'headers' => $this->punchHeaders($actorId, $civilTimezone),
        ]);
    }

    /**
     * POST /v1/punch-timer/pause?project_id=
     *
     * @return array<string, mixed>
     */
    public function pause(string $actorId, string $projectId, ?string $civilTimezone = null): array
    {
        return $this->client->sendV1('POST', '/v1/punch-timer/pause', [
            'query' => ['project_id' => $projectId],
            'headers' => $this->punchHeaders($actorId, $civilTimezone),
        ]);
    }

    /**
     * POST /v1/punch-timer/resume?project_id=
     *
     * @return array<string, mixed>
     */
    public function resume(string $actorId, string $projectId, ?string $civilTimezone = null): array
    {
        return $this->client->sendV1('POST', '/v1/punch-timer/resume', [
            'query' => ['project_id' => $projectId],
            'headers' => $this->punchHeaders($actorId, $civilTimezone),
        ]);
    }

    /**
     * GET /v1/punch-timer/days?from=&to=&project_id? — `project_id` omitted = all projects for the actor in range.
     *
     * @param array{from: string, to: string, project_id?: string|null} $query
     * @return array<string, mixed>
     */
    public function days(string $actorId, array $query, ?string $civilTimezone = null): array
    {
        $q = [
            'from' => $query['from'],
            'to' => $query['to'],
        ];
        if (isset($query['project_id']) && $query['project_id'] !== null && $query['project_id'] !== '') {
            $q['project_id'] = $query['project_id'];
        }

        return $this->client->sendV1('GET', '/v1/punch-timer/days', [
            'query' => $q,
            'headers' => $this->punchHeaders($actorId, $civilTimezone),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function punchHeaders(string $actorId, ?string $civilTimezone): array
    {
        $headers = [
            'X-Punch-Actor-Id' => trim($actorId),
        ];
        if ($civilTimezone !== null && $civilTimezone !== '') {
            $headers['X-Civil-Timezone'] = $civilTimezone;
        }

        return $headers;
    }
}
