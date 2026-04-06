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

final class MeResourceTest extends TestCase
{
    public function testGetReturnsUserPayloadAndSendsApiKeyHeaders(): void
    {
        $container = [];
        $history = Middleware::history($container);
        $payload = json_encode([
            'user' => [
                'id' => 'u1',
                'email' => 'a@b.co',
                'email_verified_at' => '2026-01-01T00:00:00.000Z',
                'created_at' => '2026-01-01T00:00:00.000Z',
            ],
        ], JSON_THROW_ON_ERROR);
        $mock = new MockHandler([new Response(200, [], $payload)]);
        $handler = HandlerStack::create($mock);
        $handler->push($history);
        $http = new Client(['handler' => $handler, 'base_uri' => 'http://127.0.0.1:3000/', 'http_errors' => false]);
        $apiKey = 'user-bound-key';
        $client = new InvoicingClient(new Config(apiKey: $apiKey), $http);

        $out = $client->me()->get();

        self::assertSame('a@b.co', $out['user']['email']);
        self::assertArrayHasKey('id', $out['user']);
        $req = $container[0]['request'];
        self::assertSame('GET', $req->getMethod());
        self::assertStringEndsWith('/v1/me', $req->getUri()->getPath());
        self::assertSame($apiKey, $req->getHeaderLine('X-API-Key'));
        self::assertSame('Bearer ' . $apiKey, $req->getHeaderLine('Authorization'));
    }

    public function testGetThrowsApiExceptionWithUserContextNotAvailableCode(): void
    {
        $body = json_encode([
            'error' => [
                'code' => 'USER_CONTEXT_NOT_AVAILABLE',
                'message' => 'This API key is not tied to a user.',
            ],
        ], JSON_THROW_ON_ERROR);
        $mock = new MockHandler([new Response(403, [], $body)]);
        $http = new Client([
            'handler' => HandlerStack::create($mock),
            'base_uri' => 'http://127.0.0.1:3000/',
            'http_errors' => false,
        ]);
        $client = new InvoicingClient(new Config(apiKey: 'global-or-unbound'), $http);

        try {
            $client->me()->get();
            self::fail('Expected ApiException');
        } catch (ApiException $e) {
            self::assertSame(403, $e->httpStatus);
            self::assertSame('USER_CONTEXT_NOT_AVAILABLE', $e->errorCode);
            self::assertStringContainsString('not tied to a user', $e->getMessage());
        }
    }
}
