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

final class InvoicingClientAuthTest extends TestCase
{
    public function testV1RequestSendsXApiKeyAndBearer(): void
    {
        $container = [];
        $history = Middleware::history($container);
        $mock = new MockHandler([
            new Response(200, [], '{"data":[]}'),
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push($history);

        $http = new Client(['handler' => $handler]);
        $config = new Config(baseUri: 'http://127.0.0.1:3000', apiKey: 'secret-key-123');
        $client = new InvoicingClient($config, $http);

        $client->getV1('/v1/clients');

        self::assertCount(1, $container);
        $req = $container[0]['request'];
        self::assertSame('GET', $req->getMethod());
        self::assertSame('secret-key-123', $req->getHeaderLine('X-API-Key'));
        self::assertSame('Bearer secret-key-123', $req->getHeaderLine('Authorization'));
    }

    public function testDefaultGuzzleClientReceivesTimeoutFromConfig(): void
    {
        $config = new Config(baseUri: 'http://127.0.0.1:3000', apiKey: 'k', timeout: 41.5);
        $client = new InvoicingClient($config);

        $ref = new \ReflectionProperty(InvoicingClient::class, 'http');
        $inner = $ref->getValue($client);
        self::assertInstanceOf(Client::class, $inner);
        self::assertSame(41.5, $inner->getConfig('timeout'));
    }
}
