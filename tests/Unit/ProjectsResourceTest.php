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

final class ProjectsResourceTest extends TestCase
{
    public function testListWithClientIdAndPerPage(): void
    {
        $container = [];
        $history = Middleware::history($container);
        $listJson = json_encode([
            'data' => [],
            'meta' => ['page' => 1, 'per_page' => 5, 'total' => 0, 'total_pages' => 0],
        ], JSON_THROW_ON_ERROR);
        $mock = new MockHandler([new Response(200, [], $listJson)]);
        $handler = HandlerStack::create($mock);
        $handler->push($history);
        $http = new Client(['handler' => $handler, 'base_uri' => 'http://127.0.0.1:3000/', 'http_errors' => false]);
        $client = new InvoicingClient(new Config(apiKey: 'k'), $http);

        $cid = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
        $client->projects()->list([
            'client_id' => $cid,
            'page' => 1,
            'per_page' => 5,
        ]);

        $req = $container[0]['request'];
        self::assertStringEndsWith('/v1/projects', $req->getUri()->getPath());
        parse_str($req->getUri()->getQuery(), $q);
        self::assertSame($cid, $q['client_id']);
        self::assertSame('1', $q['page']);
        self::assertSame('5', $q['per_page']);
        self::assertArrayNotHasKey('limit', $q);
    }

    public function testCrudPaths(): void
    {
        $container = [];
        $history = Middleware::history($container);
        $body = json_encode(['id' => 'p1', 'client_id' => 'c1', 'name' => 'P'], JSON_THROW_ON_ERROR);
        $mock = new MockHandler([
            new Response(201, [], $body),
            new Response(200, [], $body),
            new Response(200, [], $body),
            new Response(204, [], ''),
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push($history);
        $http = new Client(['handler' => $handler, 'base_uri' => 'http://127.0.0.1:3000/', 'http_errors' => false]);
        $projects = (new InvoicingClient(new Config(apiKey: 'k'), $http))->projects();

        $projects->create(['client_id' => 'c1', 'name' => 'P']);
        self::assertStringEndsWith('/v1/projects', $container[0]['request']->getUri()->getPath());

        $projects->get('p1');
        self::assertStringEndsWith('/v1/projects/p1', $container[1]['request']->getUri()->getPath());

        $projects->update('p1', ['name' => 'P2']);
        self::assertSame('PATCH', $container[2]['request']->getMethod());

        $projects->delete('p1');
        self::assertSame('DELETE', $container[3]['request']->getMethod());
    }
}
