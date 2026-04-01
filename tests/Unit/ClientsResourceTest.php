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

final class ClientsResourceTest extends TestCase
{
    public function testListUsesPerPageNotLimitAndReturnsDataAndMeta(): void
    {
        $container = [];
        $history = Middleware::history($container);
        $listJson = json_encode([
            'data' => [['id' => '550e8400-e29b-41d4-a716-446655440000', 'name' => 'Acme']],
            'meta' => ['page' => 2, 'per_page' => 10, 'total' => 1, 'total_pages' => 1],
        ], JSON_THROW_ON_ERROR);
        $mock = new MockHandler([new Response(200, [], $listJson)]);
        $handler = HandlerStack::create($mock);
        $handler->push($history);
        $http = new Client(['handler' => $handler, 'base_uri' => 'http://127.0.0.1:3000/', 'http_errors' => false]);
        $client = new InvoicingClient(new Config(apiKey: 'k'), $http);

        $out = $client->clients()->list(['page' => 2, 'per_page' => 10]);

        self::assertSame('Acme', $out['data'][0]['name']);
        self::assertSame(2, $out['meta']['page']);
        self::assertSame(10, $out['meta']['per_page']);
        $req = $container[0]['request'];
        self::assertSame('GET', $req->getMethod());
        self::assertStringEndsWith('/v1/clients', $req->getUri()->getPath());
        parse_str($req->getUri()->getQuery(), $q);
        self::assertSame('2', $q['page']);
        self::assertSame('10', $q['per_page']);
        self::assertArrayNotHasKey('limit', $q);
    }

    public function testCreateGetUpdateDelete(): void
    {
        $container = [];
        $history = Middleware::history($container);
        $created = json_encode(['id' => 'u1', 'name' => 'N', 'email' => null], JSON_THROW_ON_ERROR);
        $mock = new MockHandler([
            new Response(201, [], $created),
            new Response(200, [], $created),
            new Response(200, [], $created),
            new Response(204, [], ''),
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push($history);
        $http = new Client(['handler' => $handler, 'base_uri' => 'http://127.0.0.1:3000/', 'http_errors' => false]);
        $api = new InvoicingClient(new Config(apiKey: 'k'), $http);
        $clients = $api->clients();

        $clients->create(['name' => 'N', 'email' => null]);
        self::assertSame('POST', $container[0]['request']->getMethod());
        self::assertStringEndsWith('/v1/clients', $container[0]['request']->getUri()->getPath());

        $clients->get('u1');
        self::assertSame('GET', $container[1]['request']->getMethod());
        self::assertStringEndsWith('/v1/clients/u1', $container[1]['request']->getUri()->getPath());

        $clients->update('u1', ['name' => 'N2']);
        self::assertSame('PATCH', $container[2]['request']->getMethod());

        $clients->delete('u1');
        self::assertSame('DELETE', $container[3]['request']->getMethod());
        self::assertSame(204, $container[3]['response']->getStatusCode());
    }
}
