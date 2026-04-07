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
    private const SAMPLE_PROJECT = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';

    /**
     * @param array<int, array<string, mixed>> $container
     */
    private function clientWithHistory(MockHandler $mock, array &$container): InvoicingClient
    {
        $history = Middleware::history($container);
        $handler = HandlerStack::create($mock);
        $handler->push($history);
        $http = new Client(['handler' => $handler, 'base_uri' => 'http://127.0.0.1:3000/', 'http_errors' => false]);

        return new InvoicingClient(new Config(apiKey: 'k'), $http);
    }

    private static function statusOkJson(): string
    {
        return json_encode([
            'timezone' => 'UTC',
            'local_date' => '2026-04-06',
            'actor_id' => 'actor-1',
            'project_id' => self::SAMPLE_PROJECT,
            'status' => 'running',
            'worked_ms' => 0,
            'segment_started_at' => null,
            'updated_at' => '2026-04-06T12:00:00.000Z',
        ], JSON_THROW_ON_ERROR);
    }

    private static function daysOkJson(): string
    {
        return json_encode([
            'timezone' => 'UTC',
            'actor_id' => 'a1',
            'project_id' => null,
            'from' => '2026-04-01',
            'to' => '2026-04-06',
            'days' => [],
        ], JSON_THROW_ON_ERROR);
    }

    public function testStatusContractMethodQueryHeadersAndNoTimezoneWhenOmitted(): void
    {
        $container = [];
        $mock = new MockHandler([new Response(200, [], self::statusOkJson())]);
        $client = $this->clientWithHistory($mock, $container);
        $pid = self::SAMPLE_PROJECT;

        $client->punchTimer()->status('actor-1', $pid);

        $req = $container[0]['request'];
        self::assertSame('GET', $req->getMethod());
        self::assertStringEndsWith('/v1/punch-timer/status', $req->getUri()->getPath());
        parse_str($req->getUri()->getQuery(), $q);
        self::assertSame($pid, $q['project_id']);
        self::assertSame('actor-1', $req->getHeaderLine('X-Punch-Actor-Id'));
        self::assertFalse($req->hasHeader('X-Civil-Timezone'));
    }

    public function testStatusDoesNotSendCivilTimezoneWhenNull(): void
    {
        $mock = new MockHandler([new Response(200, [], self::statusOkJson())]);
        $container = [];
        $client = $this->clientWithHistory($mock, $container);

        $client->punchTimer()->status('actor-1', self::SAMPLE_PROJECT, null);

        $req = $container[0]['request'];
        self::assertFalse($req->hasHeader('X-Civil-Timezone'));
    }

    public function testStatusSendsCivilTimezoneWhenProvided(): void
    {
        $mock = new MockHandler([new Response(200, [], self::statusOkJson())]);
        $container = [];
        $client = $this->clientWithHistory($mock, $container);

        $client->punchTimer()->status('actor-1', self::SAMPLE_PROJECT, 'Europe/Lisbon');

        $req = $container[0]['request'];
        self::assertSame('Europe/Lisbon', $req->getHeaderLine('X-Civil-Timezone'));
        self::assertSame('actor-1', $req->getHeaderLine('X-Punch-Actor-Id'));
    }

    public function testPlayContractPostJsonHeadersAndNoTimezoneByDefault(): void
    {
        $mock = new MockHandler([new Response(200, [], self::statusOkJson())]);
        $container = [];
        $client = $this->clientWithHistory($mock, $container);
        $pid = self::SAMPLE_PROJECT;

        $client->punchTimer()->play('actor-1', $pid);

        $req = $container[0]['request'];
        self::assertSame('POST', $req->getMethod());
        self::assertStringEndsWith('/v1/punch-timer/play', $req->getUri()->getPath());
        self::assertStringContainsString('"project_id":"'.$pid.'"', str_replace('\\/', '/', (string) $req->getBody()));
        self::assertSame('actor-1', $req->getHeaderLine('X-Punch-Actor-Id'));
        self::assertFalse($req->hasHeader('X-Civil-Timezone'));
    }

    public function testPlaySendsCivilTimezoneWhenProvided(): void
    {
        $mock = new MockHandler([new Response(200, [], self::statusOkJson())]);
        $container = [];
        $client = $this->clientWithHistory($mock, $container);

        $client->punchTimer()->play('actor-1', self::SAMPLE_PROJECT, 'America/New_York');

        $req = $container[0]['request'];
        self::assertSame('America/New_York', $req->getHeaderLine('X-Civil-Timezone'));
    }

    public function testPauseContractPostQueryAndHeaders(): void
    {
        $paused = str_replace('"running"', '"paused"', self::statusOkJson());
        $mock = new MockHandler([new Response(200, [], $paused)]);
        $container = [];
        $client = $this->clientWithHistory($mock, $container);
        $pid = self::SAMPLE_PROJECT;

        $client->punchTimer()->pause('actor-1', $pid);

        $req = $container[0]['request'];
        self::assertSame('POST', $req->getMethod());
        self::assertStringEndsWith('/v1/punch-timer/pause', $req->getUri()->getPath());
        parse_str($req->getUri()->getQuery(), $q);
        self::assertSame($pid, $q['project_id']);
        self::assertSame('actor-1', $req->getHeaderLine('X-Punch-Actor-Id'));
        self::assertFalse($req->hasHeader('X-Civil-Timezone'));
    }

    public function testResumeContractPostQueryAndHeaders(): void
    {
        $mock = new MockHandler([new Response(200, [], self::statusOkJson())]);
        $container = [];
        $client = $this->clientWithHistory($mock, $container);
        $pid = self::SAMPLE_PROJECT;

        $client->punchTimer()->resume('my-actor', $pid);

        $req = $container[0]['request'];
        self::assertSame('POST', $req->getMethod());
        self::assertStringEndsWith('/v1/punch-timer/resume', $req->getUri()->getPath());
        parse_str($req->getUri()->getQuery(), $q);
        self::assertSame($pid, $q['project_id']);
        self::assertSame('my-actor', $req->getHeaderLine('X-Punch-Actor-Id'));
        self::assertFalse($req->hasHeader('X-Civil-Timezone'));
    }

    public function testDaysWithFromToAndProjectIdInQuery(): void
    {
        $mock = new MockHandler([new Response(200, [], self::daysOkJson())]);
        $container = [];
        $client = $this->clientWithHistory($mock, $container);
        $pid = self::SAMPLE_PROJECT;

        $client->punchTimer()->days('a1', ['from' => '2026-04-01', 'to' => '2026-04-06', 'project_id' => $pid]);

        $req = $container[0]['request'];
        self::assertSame('GET', $req->getMethod());
        self::assertStringEndsWith('/v1/punch-timer/days', $req->getUri()->getPath());
        parse_str($req->getUri()->getQuery(), $q);
        self::assertSame('2026-04-01', $q['from']);
        self::assertSame('2026-04-06', $q['to']);
        self::assertSame($pid, $q['project_id']);
        self::assertSame('a1', $req->getHeaderLine('X-Punch-Actor-Id'));
        self::assertFalse($req->hasHeader('X-Civil-Timezone'));
    }

    public function testDaysWithoutProjectIdOmitsQueryParam(): void
    {
        $mock = new MockHandler([new Response(200, [], self::daysOkJson())]);
        $container = [];
        $client = $this->clientWithHistory($mock, $container);

        $client->punchTimer()->days('a1', ['from' => '2026-04-01', 'to' => '2026-04-06']);

        $req = $container[0]['request'];
        parse_str($req->getUri()->getQuery(), $q);
        self::assertSame('2026-04-01', $q['from']);
        self::assertSame('2026-04-06', $q['to']);
        self::assertArrayNotHasKey('project_id', $q);
    }

    public function testPlayThenPauseStillMatchesPriorIntegrationShape(): void
    {
        $statusJson = self::statusOkJson();
        $mock = new MockHandler([
            new Response(200, [], $statusJson),
            new Response(200, [], str_replace('"running"', '"paused"', $statusJson)),
        ]);
        $container = [];
        $client = $this->clientWithHistory($mock, $container);
        $pid = self::SAMPLE_PROJECT;

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

    public function testInvalidApiKeyReturns401Unauthorized(): void
    {
        $err = json_encode([
            'error' => [
                'code' => 'UNAUTHORIZED',
                'message' =>
                    'Valid API key required: send X-API-Key or Authorization: Bearer <api_key> (JWT login token is not accepted on this route)',
            ],
        ], JSON_THROW_ON_ERROR);
        $mock = new MockHandler([new Response(401, ['Content-Type' => 'application/json'], $err)]);
        $container = [];
        $client = $this->clientWithHistory($mock, $container);

        try {
            $client->punchTimer()->status('actor-1', self::SAMPLE_PROJECT);
            self::fail('Expected ApiException');
        } catch (ApiException $e) {
            self::assertSame(401, $e->httpStatus);
            self::assertSame('UNAUTHORIZED', $e->errorCode);
            self::assertStringContainsString('Valid API key required', $e->getMessage());
        }
    }

    public function testPauseWhenIdleReturns409(): void
    {
        $err = json_encode([
            'error' => ['code' => 'CONFLICT', 'message' => 'not running'],
        ], JSON_THROW_ON_ERROR);
        $mock = new MockHandler([new Response(409, ['Content-Type' => 'application/json'], $err)]);
        $container = [];
        $client = $this->clientWithHistory($mock, $container);

        $this->expectException(ApiException::class);
        try {
            $client->punchTimer()->pause('a1', self::SAMPLE_PROJECT);
        } catch (ApiException $e) {
            self::assertSame(409, $e->httpStatus);
            self::assertSame('CONFLICT', $e->errorCode);
            throw $e;
        }
    }

    public function testEmptyActorId422MatchesApiBody(): void
    {
        $err = json_encode([
            'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'X-Punch-Actor-Id required'],
        ], JSON_THROW_ON_ERROR);
        $mock = new MockHandler([new Response(422, ['Content-Type' => 'application/json'], $err)]);
        $container = [];
        $client = $this->clientWithHistory($mock, $container);

        $this->expectException(ApiException::class);
        try {
            $client->punchTimer()->status('', self::SAMPLE_PROJECT);
        } catch (ApiException $e) {
            self::assertSame(422, $e->httpStatus);
            self::assertSame('VALIDATION_ERROR', $e->errorCode);
            self::assertStringContainsString('X-Punch-Actor-Id', $e->getMessage());
            self::assertStringContainsString('required', $e->getMessage());
            throw $e;
        }
    }

    public function testInvalidIanaTimezone422MatchesApiBody(): void
    {
        $msg = 'X-Civil-Timezone must be a valid IANA timezone';
        $err = json_encode([
            'error' => ['code' => 'VALIDATION_ERROR', 'message' => $msg],
        ], JSON_THROW_ON_ERROR);
        $mock = new MockHandler([new Response(422, ['Content-Type' => 'application/json'], $err)]);
        $container = [];
        $client = $this->clientWithHistory($mock, $container);

        $this->expectException(ApiException::class);
        try {
            $client->punchTimer()->status('a1', self::SAMPLE_PROJECT, 'Not/A/Timezone');
        } catch (ApiException $e) {
            self::assertSame(422, $e->httpStatus);
            self::assertSame('VALIDATION_ERROR', $e->errorCode);
            self::assertSame($msg, $e->getMessage());
            throw $e;
        }
    }

    public function testActorIdTrimmedInOutboundHeader(): void
    {
        $mock = new MockHandler([new Response(200, [], self::statusOkJson())]);
        $container = [];
        $client = $this->clientWithHistory($mock, $container);

        $client->punchTimer()->status("  trimmed-actor  \t", self::SAMPLE_PROJECT);

        $req = $container[0]['request'];
        self::assertSame('trimmed-actor', $req->getHeaderLine('X-Punch-Actor-Id'));
    }
}
