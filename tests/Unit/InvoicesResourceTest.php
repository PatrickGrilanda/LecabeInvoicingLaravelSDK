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

final class InvoicesResourceTest extends TestCase
{
    public function testListAllowlistQueryHasNoClientOrProjectId(): void
    {
        $container = [];
        $history = Middleware::history($container);
        $listJson = json_encode([
            'data' => [],
            'meta' => ['page' => 1, 'per_page' => 10, 'total' => 0, 'total_pages' => 0],
        ], JSON_THROW_ON_ERROR);
        $mock = new MockHandler([new Response(200, [], $listJson)]);
        $handler = HandlerStack::create($mock);
        $handler->push($history);
        $http = new Client(['handler' => $handler, 'base_uri' => 'http://127.0.0.1:3000/', 'http_errors' => false]);
        $api = new InvoicingClient(new Config(apiKey: 'k'), $http);

        $api->invoices()->list([
            'status' => 'draft',
            'issue_from' => '2026-01-01',
            'issue_to' => '2026-01-31',
            'page' => 1,
            'per_page' => 10,
        ]);

        $req = $container[0]['request'];
        self::assertStringEndsWith('/v1/invoices', $req->getUri()->getPath());
        parse_str($req->getUri()->getQuery(), $q);
        self::assertSame('draft', $q['status']);
        self::assertSame('2026-01-01', $q['issue_from']);
        self::assertSame('2026-01-31', $q['issue_to']);
        self::assertSame('1', $q['page']);
        self::assertSame('10', $q['per_page']);
        self::assertArrayNotHasKey('client_id', $q);
        self::assertArrayNotHasKey('project_id', $q);
    }

    public function testCreateGetUpdateDelete(): void
    {
        $container = [];
        $history = Middleware::history($container);
        $inv = json_encode(['id' => 'i1', 'status' => 'draft', 'lines' => []], JSON_THROW_ON_ERROR);
        $mock = new MockHandler([
            new Response(201, [], $inv),
            new Response(200, [], $inv),
            new Response(200, [], $inv),
            new Response(204, [], ''),
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push($history);
        $http = new Client(['handler' => $handler, 'base_uri' => 'http://127.0.0.1:3000/', 'http_errors' => false]);
        $invoices = (new InvoicingClient(new Config(apiKey: 'k'), $http))->invoices();

        $invoices->create(['client_name' => 'A', 'issue_date' => '2026-03-01']);
        self::assertSame('POST', $container[0]['request']->getMethod());
        self::assertStringEndsWith('/v1/invoices', $container[0]['request']->getUri()->getPath());

        $invoices->get('i1');
        self::assertStringEndsWith('/v1/invoices/i1', $container[1]['request']->getUri()->getPath());

        $invoices->update('i1', ['notes' => 'x']);
        self::assertSame('PATCH', $container[2]['request']->getMethod());

        $invoices->delete('i1');
        self::assertSame('DELETE', $container[3]['request']->getMethod());
    }
}
