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
use Lecabe\Invoicing\System\Health;
use Lecabe\Invoicing\System\Ready;
use Lecabe\Invoicing\Tests\TestCase;

final class HealthReadyTest extends TestCase
{
    public function testHealthUsesRootPathWithoutAuthHeaders(): void
    {
        $container = [];
        $history = Middleware::history($container);
        $payload = json_encode([
            'status' => 'ok',
            'service' => 'lecabe-invoicing',
            'timestamp' => '2026-01-01T00:00:00.000Z',
        ], JSON_THROW_ON_ERROR);
        $mock = new MockHandler([new Response(200, [], $payload)]);
        $handler = HandlerStack::create($mock);
        $handler->push($history);

        $http = new Client([
            'handler' => $handler,
            'base_uri' => 'http://127.0.0.1:3000/',
            'http_errors' => false,
        ]);
        $client = new InvoicingClient(new Config(apiKey: 'should-not-leak'), $http);

        $data = $client->health();

        self::assertSame('ok', $data['status']);
        self::assertSame('lecabe-invoicing', $data['service']);
        self::assertCount(1, $container);
        $req = $container[0]['request'];
        self::assertStringEndsWith('/health', $req->getUri()->getPath());
        self::assertSame('', $req->getHeaderLine('X-API-Key'));
        self::assertSame('', $req->getHeaderLine('Authorization'));
    }

    public function testReadyViaSystemClass(): void
    {
        $payload = json_encode([
            'status' => 'ready',
            'database' => 'ok',
            'timestamp' => '2026-01-01T00:00:00.000Z',
        ], JSON_THROW_ON_ERROR);
        $mock = new MockHandler([new Response(200, [], $payload)]);
        $http = new Client([
            'handler' => HandlerStack::create($mock),
            'base_uri' => 'http://127.0.0.1:3000/',
            'http_errors' => false,
        ]);
        $client = new InvoicingClient(new Config(), $http);

        $ready = new Ready($client);
        $data = $ready->get();

        self::assertSame('ready', $data['status']);
        self::assertSame('ok', $data['database']);
    }

    public function testHealthClassGet(): void
    {
        $payload = '{"status":"ok","service":"lecabe-invoicing","timestamp":"2026-01-01T00:00:00.000Z"}';
        $mock = new MockHandler([new Response(200, [], $payload)]);
        $http = new Client([
            'handler' => HandlerStack::create($mock),
            'base_uri' => 'http://127.0.0.1:3000/',
            'http_errors' => false,
        ]);
        $client = new InvoicingClient(new Config(), $http);

        $health = new Health($client);
        self::assertSame('ok', $health->get()['status']);
    }
}
