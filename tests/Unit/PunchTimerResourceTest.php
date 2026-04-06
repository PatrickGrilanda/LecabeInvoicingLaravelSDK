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

final class PunchTimerResourceTest extends TestCase
{
    public function testPlayThenPauseSendsActorHeaderAndProjectBody(): void
    {
        $container = [];
        $history = Middleware::history($container);
        $statusJson = json_encode([
            'timezone' => 'UTC',
            'local_date' => '2026-04-06',
            'actor_id' => 'actor-1',
            'project_id' => 'p1',
            'status' => 'running',
            'segment_started_at' => null,
            'updated_at' => null,
        ], JSON_THROW_ON_ERROR);
        $mock = new MockHandler([
            new Response(200, [], $statusJson),
            new Response(200, [], str_replace('"running"', '"paused"', $statusJson)),
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push($history);
        $http = new Client(['handler' => $handler, 'base_uri' => 'http://127.0.0.1:3000/', 'http_errors' => false]);
        $client = new InvoicingClient(new Config(apiKey: 'k'), $http);
        $pid = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';

        $client->punchTimer()->play('actor-1', $pid);
        $client->punchTimer()->pause('actor-1', $pid);

        $play = $container[0]['request'];
        self::assertSame('POST', $play->getMethod());
        self::assertStringEndsWith('/v1/punch-timer/play', $play->getUri()->getPath());
        self::assertSame('actor-1', $play->getHeaderLine('X-Punch-Actor-Id'));
        self::assertStringContainsString($pid, (string) $play->getBody());

        $pause = $container[1]['request'];
        self::assertSame('POST', $pause->getMethod());
        self::assertStringEndsWith('/v1/punch-timer/pause', $pause->getUri()->getPath());
        parse_str($pause->getUri()->getQuery(), $q);
        self::assertSame($pid, $q['project_id']);
        self::assertSame('actor-1', $pause->getHeaderLine('X-Punch-Actor-Id'));
    }

    public function testOptionalCivilTimezoneHeader(): void
    {
        $container = [];
        $history = Middleware::history($container);
        $json = json_encode(['ok' => true], JSON_THROW_ON_ERROR);
        $mock = new MockHandler([new Response(200, [], $json)]);
        $handler = HandlerStack::create($mock);
        $handler->push($history);
        $http = new Client(['handler' => $handler, 'base_uri' => 'http://127.0.0.1:3000/', 'http_errors' => false]);
        $client = new InvoicingClient(new Config(apiKey: 'k'), $http);

        $client->punchTimer()->status('a1', 'p1', 'Europe/Lisbon');

        $req = $container[0]['request'];
        self::assertSame('Europe/Lisbon', $req->getHeaderLine('X-Civil-Timezone'));
    }

    public function testDaysWithoutProjectId(): void
    {
        $container = [];
        $history = Middleware::history($container);
        $json = json_encode(['timezone' => 'UTC', 'actor_id' => 'a1', 'project_id' => null, 'from' => '2026-04-01', 'to' => '2026-04-06', 'days' => []], JSON_THROW_ON_ERROR);
        $mock = new MockHandler([new Response(200, [], $json)]);
        $handler = HandlerStack::create($mock);
        $handler->push($history);
        $http = new Client(['handler' => $handler, 'base_uri' => 'http://127.0.0.1:3000/', 'http_errors' => false]);
        $client = new InvoicingClient(new Config(apiKey: 'k'), $http);

        $client->punchTimer()->days('a1', ['from' => '2026-04-01', 'to' => '2026-04-06']);

        $req = $container[0]['request'];
        parse_str($req->getUri()->getQuery(), $q);
        self::assertSame('2026-04-01', $q['from']);
        self::assertSame('2026-04-06', $q['to']);
        self::assertArrayNotHasKey('project_id', $q);
    }

    public function testPauseWhenIdleReturns409(): void
    {
        $err = json_encode([
            'error' => ['code' => 'CONFLICT', 'message' => 'not running'],
        ], JSON_THROW_ON_ERROR);
        $mock = new MockHandler([new Response(409, ['Content-Type' => 'application/json'], $err)]);
        $handler = HandlerStack::create($mock);
        $http = new Client(['handler' => $handler, 'base_uri' => 'http://127.0.0.1:3000/', 'http_errors' => false]);
        $client = new InvoicingClient(new Config(apiKey: 'k'), $http);

        $this->expectException(ApiException::class);
        try {
            $client->punchTimer()->pause('a1', 'p1');
        } catch (ApiException $e) {
            self::assertSame(409, $e->httpStatus);
            self::assertSame('CONFLICT', $e->errorCode);
            throw $e;
        }
    }

    public function testMissingActorHeader422(): void
    {
        $err = json_encode([
            'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'X-Punch-Actor-Id required'],
        ], JSON_THROW_ON_ERROR);
        $mock = new MockHandler([new Response(422, ['Content-Type' => 'application/json'], $err)]);
        $handler = HandlerStack::create($mock);
        $http = new Client(['handler' => $handler, 'base_uri' => 'http://127.0.0.1:3000/', 'http_errors' => false]);
        $client = new InvoicingClient(new Config(apiKey: 'k'), $http);

        $this->expectException(ApiException::class);
        try {
            $client->punchTimer()->status('', 'p1');
        } catch (ApiException $e) {
            self::assertSame(422, $e->httpStatus);
            throw $e;
        }
    }
}
