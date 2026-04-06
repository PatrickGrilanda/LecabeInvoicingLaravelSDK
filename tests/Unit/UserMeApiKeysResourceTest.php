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

final class UserMeApiKeysResourceTest extends TestCase
{
    public function testCreateSendsJwtBearerAndOmitsApiKeyHeader(): void
    {
        $container = [];
        $history = Middleware::history($container);
        $payload = json_encode([
            'id' => 'key-uuid',
            'api_key' => 'lk_live_xxx',
            'label' => 'x',
            'expires_at' => null,
        ], JSON_THROW_ON_ERROR);
        $mock = new MockHandler([new Response(201, [], $payload)]);
        $handler = HandlerStack::create($mock);
        $handler->push($history);
        $http = new Client(['handler' => $handler, 'base_uri' => 'http://127.0.0.1:3000/', 'http_errors' => false]);
        $client = new InvoicingClient(new Config(apiKey: 'must-not-appear-here'), $http);
        $jwt = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJ1MSJ9.sig';

        $out = $client->userMeApiKeys()->create($jwt, ['label' => 'x']);

        self::assertSame('key-uuid', $out['id']);
        self::assertSame('lk_live_xxx', $out['api_key']);
        self::assertCount(1, $container);
        $req = $container[0]['request'];
        self::assertSame('POST', $req->getMethod());
        self::assertStringEndsWith('/v1/users/me/api-keys', $req->getUri()->getPath());
        self::assertSame('', $req->getHeaderLine('X-API-Key'));
        self::assertSame('Bearer ' . $jwt, $req->getHeaderLine('Authorization'));
        $decoded = json_decode((string) $req->getBody(), true);
        self::assertIsArray($decoded);
        self::assertSame('x', $decoded['label']);
    }

    public function testCreateWithEmptyBodyOmitsJsonOption(): void
    {
        $container = [];
        $history = Middleware::history($container);
        $payload = json_encode(['id' => 'k1', 'api_key' => 'secret', 'label' => null, 'expires_at' => null], JSON_THROW_ON_ERROR);
        $mock = new MockHandler([new Response(201, [], $payload)]);
        $handler = HandlerStack::create($mock);
        $handler->push($history);
        $http = new Client(['handler' => $handler, 'base_uri' => 'http://127.0.0.1:3000/', 'http_errors' => false]);
        $client = new InvoicingClient(new Config(apiKey: 'must-not-appear'), $http);

        $client->userMeApiKeys()->create('jwt-token');

        $req = $container[0]['request'];
        self::assertSame('', (string) $req->getBody());
    }

    public function testEmailNotVerifiedPropagatesErrorCode(): void
    {
        $json = json_encode([
            'error' => [
                'code' => 'EMAIL_NOT_VERIFIED',
                'message' => 'Verify your email first',
            ],
        ], JSON_THROW_ON_ERROR);
        $mock = new MockHandler([new Response(403, ['Content-Type' => 'application/json'], $json)]);
        $handler = HandlerStack::create($mock);
        $http = new Client(['handler' => $handler, 'base_uri' => 'http://127.0.0.1:3000/', 'http_errors' => false]);
        $client = new InvoicingClient(new Config(apiKey: 'x'), $http);

        try {
            $client->userMeApiKeys()->create('jwt');
            self::fail('Expected ApiException');
        } catch (ApiException $e) {
            self::assertSame(403, $e->httpStatus);
            self::assertSame('EMAIL_NOT_VERIFIED', $e->errorCode);
        }
    }

    public function testUnauthorizedPropagatesErrorCode(): void
    {
        $json = json_encode([
            'error' => [
                'code' => 'UNAUTHORIZED',
                'message' => 'Invalid or missing JWT subject',
            ],
        ], JSON_THROW_ON_ERROR);
        $mock = new MockHandler([new Response(401, ['Content-Type' => 'application/json'], $json)]);
        $handler = HandlerStack::create($mock);
        $http = new Client(['handler' => $handler, 'base_uri' => 'http://127.0.0.1:3000/', 'http_errors' => false]);
        $client = new InvoicingClient(new Config(apiKey: 'x'), $http);

        try {
            $client->userMeApiKeys()->create('bad-jwt');
            self::fail('Expected ApiException');
        } catch (ApiException $e) {
            self::assertSame(401, $e->httpStatus);
            self::assertSame('UNAUTHORIZED', $e->errorCode);
        }
    }
}
