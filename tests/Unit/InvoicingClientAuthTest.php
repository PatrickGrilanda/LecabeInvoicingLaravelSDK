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

    public function testSendV1SendsXApiKeyAndBearer(): void
    {
        $container = [];
        $history = Middleware::history($container);
        $mock = new MockHandler([
            new Response(200, [], '{"ok":true}'),
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push($history);

        $http = new Client(['handler' => $handler]);
        $config = new Config(baseUri: 'http://127.0.0.1:3000', apiKey: 'invoicing-api-key');
        $client = new InvoicingClient($config, $http);

        $client->sendV1('POST', '/v1/example', ['json' => ['a' => 1]]);

        self::assertCount(1, $container);
        $req = $container[0]['request'];
        self::assertSame('POST', $req->getMethod());
        self::assertSame('invoicing-api-key', $req->getHeaderLine('X-API-Key'));
        self::assertSame('Bearer invoicing-api-key', $req->getHeaderLine('Authorization'));
    }

    public function testSendV1MergesCustomHeadersWithV1Auth(): void
    {
        $container = [];
        $history = Middleware::history($container);
        $mock = new MockHandler([
            new Response(200, [], '{"status":"idle"}'),
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push($history);

        $http = new Client(['handler' => $handler]);
        $config = new Config(baseUri: 'http://127.0.0.1:3000', apiKey: 'punch-merge-key');
        $client = new InvoicingClient($config, $http);

        $pid = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
        $client->sendV1('GET', '/v1/punch-timer/status', [
            'query' => ['project_id' => $pid],
            'headers' => ['X-Punch-Actor-Id' => 'actor-1'],
        ]);

        self::assertCount(1, $container);
        $req = $container[0]['request'];
        self::assertSame('GET', $req->getMethod());
        self::assertStringEndsWith('/v1/punch-timer/status', $req->getUri()->getPath());
        parse_str($req->getUri()->getQuery(), $q);
        self::assertSame($pid, $q['project_id']);
        self::assertSame('punch-merge-key', $req->getHeaderLine('X-API-Key'));
        self::assertSame('Bearer punch-merge-key', $req->getHeaderLine('Authorization'));
        self::assertSame('actor-1', $req->getHeaderLine('X-Punch-Actor-Id'));
    }

    public function testSendV1WithJwtOmitsXApiKeyAndUsesJwtBearer(): void
    {
        $container = [];
        $history = Middleware::history($container);
        $mock = new MockHandler([
            new Response(200, [], '{"token":"ok"}'),
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push($history);

        $http = new Client(['handler' => $handler]);
        $config = new Config(baseUri: 'http://127.0.0.1:3000', apiKey: 'different-from-jwt');
        $client = new InvoicingClient($config, $http);

        $client->sendV1WithJwt('GET', '/v1/me', 'jwt-token-xyz');

        self::assertCount(1, $container);
        $req = $container[0]['request'];
        self::assertSame('', $req->getHeaderLine('X-API-Key'));
        self::assertSame('Bearer jwt-token-xyz', $req->getHeaderLine('Authorization'));
    }

    public function testSendV1WithBasicOmitsXApiKeyAndSetsBasicAuthorization(): void
    {
        $container = [];
        $history = Middleware::history($container);
        $mock = new MockHandler([
            new Response(200, [], '{}'),
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push($history);

        $http = new Client(['handler' => $handler]);
        $config = new Config(baseUri: 'http://127.0.0.1:3000', apiKey: 'only-for-other-paths');
        $client = new InvoicingClient($config, $http);

        $client->sendV1WithBasic('POST', '/v1/admin/api-keys', 'user@example.com', 's3cret');

        self::assertCount(1, $container);
        $req = $container[0]['request'];
        self::assertSame('', $req->getHeaderLine('X-API-Key'));
        $expected = 'Basic ' . base64_encode('user@example.com:s3cret');
        self::assertSame($expected, $req->getHeaderLine('Authorization'));
    }

    public function testSendV1PublicOmitsInvoicingAuthHeaders(): void
    {
        $container = [];
        $history = Middleware::history($container);
        $mock = new MockHandler([
            new Response(200, [], '{"registered":true}'),
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push($history);

        $http = new Client(['handler' => $handler]);
        $config = new Config(baseUri: 'http://127.0.0.1:3000', apiKey: 'should-not-leak');
        $client = new InvoicingClient($config, $http);

        $client->sendV1Public('POST', '/v1/auth/register', ['json' => ['email' => 'a@b.co']]);

        self::assertCount(1, $container);
        $req = $container[0]['request'];
        self::assertSame('', $req->getHeaderLine('X-API-Key'));
        self::assertSame('', $req->getHeaderLine('Authorization'));
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
