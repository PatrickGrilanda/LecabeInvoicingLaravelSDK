<?php

declare(strict_types=1);

namespace Lecabe\Invoicing\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Lecabe\Invoicing\Config;
use Lecabe\Invoicing\InvoicingClient;
use Lecabe\Invoicing\Tests\TestCase;

final class TimeEntriesResourceTest extends TestCase
{
    public function testListWithFiltersAndPagination(): void
    {
        $container = [];
        $history = Middleware::history($container);
        $listJson = json_encode([
            'data' => [],
            'meta' => ['page' => 1, 'per_page' => 20, 'total' => 0, 'total_pages' => 0],
        ], JSON_THROW_ON_ERROR);
        $mock = new MockHandler([new Response(200, [], $listJson)]);
        $handler = HandlerStack::create($mock);
        $handler->push($history);
        $http = new Client(['handler' => $handler, 'base_uri' => 'http://127.0.0.1:3000/', 'http_errors' => false]);
        $te = (new InvoicingClient(new Config(apiKey: 'k'), $http))->timeEntries();

        $te->list([
            'project_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'client_id' => 'bbbbbbbb-cccc-dddd-eeee-ffffffffffff',
            'from' => '2026-01-01T00:00:00.000Z',
            'to' => '2026-02-01T00:00:00.000Z',
            'billable' => true,
            'unbilled_only' => false,
            'page' => 1,
            'per_page' => 15,
        ]);

        $req = $container[0]['request'];
        self::assertStringEndsWith('/v1/time-entries', $req->getUri()->getPath());
        parse_str($req->getUri()->getQuery(), $q);
        self::assertArrayHasKey('project_id', $q);
        self::assertArrayHasKey('client_id', $q);
        self::assertArrayHasKey('from', $q);
        self::assertArrayHasKey('to', $q);
        self::assertSame('true', $q['billable']);
        self::assertSame('false', $q['unbilled_only']);
        self::assertSame('1', $q['page']);
        self::assertSame('15', $q['per_page']);
    }

    public function testCreateBodyContainsOccurredAtNotStartedAt(): void
    {
        $container = [];
        $history = Middleware::history($container);
        $entry = json_encode([
            'id' => 't1',
            'project_id' => 'p1',
            'occurred_at' => '2026-03-01T10:00:00.000Z',
            'duration_minutes' => 60,
            'billable' => true,
        ], JSON_THROW_ON_ERROR);
        $mock = new MockHandler([new Response(201, [], $entry)]);
        $handler = HandlerStack::create($mock);
        $handler->push($history);
        $http = new Client(['handler' => $handler, 'base_uri' => 'http://127.0.0.1:3000/', 'http_errors' => false]);
        $te = (new InvoicingClient(new Config(apiKey: 'k'), $http))->timeEntries();

        $te->create([
            'project_id' => 'p1',
            'occurred_at' => '2026-03-01T10:00:00.000Z',
            'duration_minutes' => 60,
        ]);

        $raw = (string) $container[0]['request']->getBody();
        self::assertStringContainsString('occurred_at', $raw);
        self::assertStringNotContainsString('started_at', $raw);
    }

    public function testUpdatePatchUsesOccurredAt(): void
    {
        $container = [];
        $history = Middleware::history($container);
        $entry = json_encode(['id' => 't1', 'project_id' => 'p1', 'occurred_at' => '2026-03-02T10:00:00.000Z', 'duration_minutes' => 30, 'billable' => true], JSON_THROW_ON_ERROR);
        $mock = new MockHandler([new Response(200, [], $entry)]);
        $handler = HandlerStack::create($mock);
        $handler->push($history);
        $http = new Client(['handler' => $handler, 'base_uri' => 'http://127.0.0.1:3000/', 'http_errors' => false]);
        $te = (new InvoicingClient(new Config(apiKey: 'k'), $http))->timeEntries();

        $te->update('t1', ['occurred_at' => '2026-03-02T10:00:00.000Z']);

        $raw = (string) $container[0]['request']->getBody();
        self::assertStringContainsString('occurred_at', $raw);
        self::assertStringNotContainsString('started_at', $raw);
        self::assertSame('PATCH', $container[0]['request']->getMethod());
        self::assertStringEndsWith('/v1/time-entries/t1', $container[0]['request']->getUri()->getPath());
    }

    public function testSourceHasNoStartedAt(): void
    {
        $path = dirname(__DIR__, 2) . '/src/Resources/TimeEntries.php';
        $src = file_get_contents($path);
        self::assertIsString($src);
        self::assertStringNotContainsString('started_at', $src);
    }
}
