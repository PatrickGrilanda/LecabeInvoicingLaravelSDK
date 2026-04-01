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

final class InvoiceLinesResourceTest extends TestCase
{
    public function testRecalculatePostsToRecalculatePath(): void
    {
        $container = [];
        $history = Middleware::history($container);
        $inv = json_encode(['id' => 'inv1', 'lines' => [], 'total_cents' => 100], JSON_THROW_ON_ERROR);
        $mock = new MockHandler([new Response(200, [], $inv)]);
        $handler = HandlerStack::create($mock);
        $handler->push($history);
        $http = new Client(['handler' => $handler, 'base_uri' => 'http://127.0.0.1:3000/', 'http_errors' => false]);
        $lines = (new InvoicingClient(new Config(apiKey: 'k'), $http))->invoiceLines();

        $out = $lines->recalculate('inv1');

        self::assertSame(100, $out['total_cents']);
        $req = $container[0]['request'];
        self::assertSame('POST', $req->getMethod());
        self::assertStringEndsWith('/v1/invoices/inv1/recalculate', $req->getUri()->getPath());
    }

    public function testCreateSendsUnitPriceCentsAndQuantity(): void
    {
        $container = [];
        $history = Middleware::history($container);
        $line = json_encode(['id' => 'l1', 'quantity' => 2, 'unit_price_cents' => 5000], JSON_THROW_ON_ERROR);
        $mock = new MockHandler([new Response(201, [], $line)]);
        $handler = HandlerStack::create($mock);
        $handler->push($history);
        $http = new Client(['handler' => $handler, 'base_uri' => 'http://127.0.0.1:3000/', 'http_errors' => false]);
        $lines = (new InvoicingClient(new Config(apiKey: 'k'), $http))->invoiceLines();

        $lines->create('inv1', [
            'description' => 'Work',
            'quantity' => 2,
            'unit_price_cents' => 5000,
        ]);

        $raw = (string) $container[0]['request']->getBody();
        self::assertStringContainsString('unit_price_cents', $raw);
        self::assertStringContainsString('quantity', $raw);
        self::assertStringContainsString('/v1/invoices/inv1/lines', $container[0]['request']->getUri()->getPath());
    }

    public function testUpdatePatchAndDelete(): void
    {
        $container = [];
        $history = Middleware::history($container);
        $line = json_encode(['id' => 'l1'], JSON_THROW_ON_ERROR);
        $mock = new MockHandler([
            new Response(200, [], $line),
            new Response(204, [], ''),
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push($history);
        $http = new Client(['handler' => $handler, 'base_uri' => 'http://127.0.0.1:3000/', 'http_errors' => false]);
        $lines = (new InvoicingClient(new Config(apiKey: 'k'), $http))->invoiceLines();

        $lines->update('inv1', 'l1', ['quantity' => 3]);
        self::assertSame('PATCH', $container[0]['request']->getMethod());
        self::assertStringContainsString('/lines/l1', $container[0]['request']->getUri()->getPath());

        $lines->delete('inv1', 'l1');
        self::assertSame('DELETE', $container[1]['request']->getMethod());
    }

    public function testListFetchesInvoiceLines(): void
    {
        $container = [];
        $history = Middleware::history($container);
        $inv = json_encode([
            'id' => 'inv1',
            'lines' => [['id' => 'l1', 'description' => 'Row']],
        ], JSON_THROW_ON_ERROR);
        $mock = new MockHandler([new Response(200, [], $inv)]);
        $handler = HandlerStack::create($mock);
        $handler->push($history);
        $http = new Client(['handler' => $handler, 'base_uri' => 'http://127.0.0.1:3000/', 'http_errors' => false]);
        $lines = (new InvoicingClient(new Config(apiKey: 'k'), $http))->invoiceLines();

        $embedded = $lines->list('inv1');

        self::assertCount(1, $embedded);
        self::assertSame('Row', $embedded[0]['description']);
        self::assertStringEndsWith('/v1/invoices/inv1', $container[0]['request']->getUri()->getPath());
    }
}
