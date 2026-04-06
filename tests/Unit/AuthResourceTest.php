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

final class AuthResourceTest extends TestCase
{
    public function testRegisterUsesPublicTransportPostRegister(): void
    {
        $container = [];
        $history = Middleware::history($container);
        $payload = json_encode(['user' => ['id' => 'u1', 'email' => 'a@b.co']], JSON_THROW_ON_ERROR);
        $mock = new MockHandler([new Response(201, [], $payload)]);
        $handler = HandlerStack::create($mock);
        $handler->push($history);
        $http = new Client(['handler' => $handler, 'base_uri' => 'http://127.0.0.1:3000/', 'http_errors' => false]);
        $client = new InvoicingClient(new Config(apiKey: 'must-not-appear-on-public'), $http);

        $out = $client->auth()->register(['email' => 'a@b.co', 'password' => 'secret123']);

        self::assertArrayHasKey('user', $out);
        self::assertCount(1, $container);
        $req = $container[0]['request'];
        self::assertSame('POST', $req->getMethod());
        self::assertStringEndsWith('/v1/auth/register', $req->getUri()->getPath());
        self::assertSame('', $req->getHeaderLine('X-API-Key'));
        self::assertSame('', $req->getHeaderLine('Authorization'));
        $decoded = json_decode((string) $req->getBody(), true);
        self::assertIsArray($decoded);
        self::assertSame('a@b.co', $decoded['email']);
        self::assertSame('secret123', $decoded['password']);
    }

    public function testLoginUsesPublicTransportPostLogin(): void
    {
        $container = [];
        $history = Middleware::history($container);
        $payload = json_encode([
            'access_token' => 'jwt-here',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ], JSON_THROW_ON_ERROR);
        $mock = new MockHandler([new Response(200, [], $payload)]);
        $handler = HandlerStack::create($mock);
        $handler->push($history);
        $http = new Client(['handler' => $handler, 'base_uri' => 'http://127.0.0.1:3000/', 'http_errors' => false]);
        $client = new InvoicingClient(new Config(apiKey: 'must-not-appear-on-public'), $http);

        $out = $client->auth()->login(['email' => 'a@b.co', 'password' => 'secret123']);

        self::assertSame('jwt-here', $out['access_token']);
        $req = $container[0]['request'];
        self::assertSame('POST', $req->getMethod());
        self::assertStringEndsWith('/v1/auth/login', $req->getUri()->getPath());
        self::assertSame('', $req->getHeaderLine('X-API-Key'));
        self::assertSame('', $req->getHeaderLine('Authorization'));
    }

    public function testVerifyEmailUsesPublicGetWithTokenQuery(): void
    {
        $container = [];
        $history = Middleware::history($container);
        $payload = json_encode(['verified' => true, 'email' => 'a@b.co'], JSON_THROW_ON_ERROR);
        $mock = new MockHandler([new Response(200, [], $payload)]);
        $handler = HandlerStack::create($mock);
        $handler->push($history);
        $http = new Client(['handler' => $handler, 'base_uri' => 'http://127.0.0.1:3000/', 'http_errors' => false]);
        $client = new InvoicingClient(new Config(apiKey: 'must-not-appear-on-public'), $http);

        $out = $client->auth()->verifyEmail('testtoken');

        self::assertTrue($out['verified']);
        $req = $container[0]['request'];
        self::assertSame('GET', $req->getMethod());
        self::assertStringEndsWith('/v1/auth/verify-email', $req->getUri()->getPath());
        parse_str($req->getUri()->getQuery(), $q);
        self::assertSame('testtoken', $q['token']);
        self::assertSame('', $req->getHeaderLine('X-API-Key'));
        self::assertSame('', $req->getHeaderLine('Authorization'));
    }

    public function testResendVerificationUsesJwtOnlyNoApiKey(): void
    {
        $container = [];
        $history = Middleware::history($container);
        $payload = json_encode(['sent' => true], JSON_THROW_ON_ERROR);
        $mock = new MockHandler([new Response(200, [], $payload)]);
        $handler = HandlerStack::create($mock);
        $handler->push($history);
        $http = new Client(['handler' => $handler, 'base_uri' => 'http://127.0.0.1:3000/', 'http_errors' => false]);
        $client = new InvoicingClient(new Config(apiKey: 'must-not-appear-here'), $http);
        $jwt = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJ1MSJ9.sig';

        $out = $client->auth()->resendVerification($jwt);

        self::assertTrue($out['sent']);
        $req = $container[0]['request'];
        self::assertSame('POST', $req->getMethod());
        self::assertStringEndsWith('/v1/auth/resend-verification', $req->getUri()->getPath());
        self::assertSame('', $req->getHeaderLine('X-API-Key'));
        self::assertSame('Bearer ' . $jwt, $req->getHeaderLine('Authorization'));
    }
}
