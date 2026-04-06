<?php

declare(strict_types=1);

namespace Lecabe\Invoicing\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Lecabe\Invoicing\Config;
use Lecabe\Invoicing\Exception\ApiException;
use Lecabe\Invoicing\InvoicingClient;
use Lecabe\Invoicing\Tests\TestCase;

final class AdminApiKeysResourceTest extends TestCase
{
    public function testCreateSendsBasicAuthAndOmitsApiKey(): void
    {
        $container = [];
        $history = Middleware::history($container);
        $payload = json_encode([
            'id' => 'admin-key-id',
            'api_key' => 'lk_admin_xxx',
            'label' => 'admin-key',
            'expires_at' => null,
        ], JSON_THROW_ON_ERROR);
        $mock = new MockHandler([new Response(201, [], $payload)]);
        $handler = HandlerStack::create($mock);
        $handler->push($history);
        $http = new Client(['handler' => $handler, 'base_uri' => 'http://127.0.0.1:3000/', 'http_errors' => false]);
        $client = new InvoicingClient(new Config(apiKey: 'must-not-appear-here'), $http);

        $out = $client->adminApiKeys()->create('user@x.test', 'secret', ['label' => 'admin-key']);

        self::assertSame('admin-key-id', $out['id']);
        self::assertCount(1, $container);
        $req = $container[0]['request'];
        self::assertSame('POST', $req->getMethod());
        self::assertStringEndsWith('/v1/admin/api-keys', $req->getUri()->getPath());
        self::assertSame('', $req->getHeaderLine('X-API-Key'));
        $auth = $req->getHeaderLine('Authorization');
        self::assertStringStartsWith('Basic ', $auth);
        $decoded = base64_decode(substr($auth, 6), true);
        self::assertSame('user@x.test:secret', $decoded);
        $body = json_decode((string) $req->getBody(), true);
        self::assertIsArray($body);
        self::assertSame('admin-key', $body['label']);
    }

    public function testUnauthorizedPropagatesErrorCode(): void
    {
        $json = json_encode([
            'error' => [
                'code' => 'UNAUTHORIZED',
                'message' => 'Invalid credentials',
            ],
        ], JSON_THROW_ON_ERROR);
        $mock = new MockHandler([new Response(401, ['Content-Type' => 'application/json'], $json)]);
        $handler = HandlerStack::create($mock);
        $http = new Client(['handler' => $handler, 'base_uri' => 'http://127.0.0.1:3000/', 'http_errors' => false]);
        $client = new InvoicingClient(new Config(apiKey: 'x'), $http);

        try {
            $client->adminApiKeys()->create('u@x.test', 'wrong');
            self::fail('Expected ApiException');
        } catch (ApiException $e) {
            self::assertSame(401, $e->httpStatus);
            self::assertSame('UNAUTHORIZED', $e->errorCode);
        }
    }

    public function testEmailNotVerifiedPropagatesErrorCode(): void
    {
        $json = json_encode([
            'error' => [
                'code' => 'EMAIL_NOT_VERIFIED',
                'message' => 'Verify email first',
            ],
        ], JSON_THROW_ON_ERROR);
        $mock = new MockHandler([new Response(403, ['Content-Type' => 'application/json'], $json)]);
        $handler = HandlerStack::create($mock);
        $http = new Client(['handler' => $handler, 'base_uri' => 'http://127.0.0.1:3000/', 'http_errors' => false]);
        $client = new InvoicingClient(new Config(apiKey: 'x'), $http);

        try {
            $client->adminApiKeys()->create('u@x.test', 'okpass');
            self::fail('Expected ApiException');
        } catch (ApiException $e) {
            self::assertSame(403, $e->httpStatus);
            self::assertSame('EMAIL_NOT_VERIFIED', $e->errorCode);
        }
    }
}
