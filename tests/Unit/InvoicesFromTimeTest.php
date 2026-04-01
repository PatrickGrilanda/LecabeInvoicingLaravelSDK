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

final class InvoicesFromTimeTest extends TestCase
{
    public function testPostsToFromTimePath(): void
    {
        $container = [];
        $history = Middleware::history($container);
        $mock = new MockHandler([
            new Response(201, [], json_encode(['id' => 'inv', 'status' => 'draft', 'lines' => []], JSON_THROW_ON_ERROR)),
        ]);
        $handler = HandlerStack::create($mock);
        $handler->push($history);
        $http = new Client([
            'handler' => $handler,
            'base_uri' => 'http://127.0.0.1:3000/',
            'http_errors' => false,
        ]);
        $api = new InvoicingClient(new Config(apiKey: 'k'), $http);

        $api->invoices()->createFromTime([
            'project_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'time_entry_ids' => ['bbbbbbbb-cccc-dddd-eeee-ffffffffffff'],
        ]);

        $req = $container[0]['request'];
        self::assertSame('POST', $req->getMethod());
        self::assertStringEndsWith('/v1/invoices/from-time', $req->getUri()->getPath());
    }

    public function test422MapsFromTimeInvalidSelectionWithDetails(): void
    {
        $body = json_encode([
            'error' => [
                'code' => 'FROM_TIME_INVALID_SELECTION',
                'message' => 'Some entries invalid',
                'details' => [
                    ['time_entry_id' => 't1', 'code' => 'ALREADY_BILLED', 'message' => 'x'],
                ],
            ],
        ], JSON_THROW_ON_ERROR);
        $mock = new MockHandler([new Response(422, [], $body)]);
        $http = new Client([
            'handler' => HandlerStack::create($mock),
            'base_uri' => 'http://127.0.0.1:3000/',
            'http_errors' => false,
        ]);
        $api = new InvoicingClient(new Config(apiKey: 'k'), $http);

        try {
            $api->invoices()->createFromTime([
                'client_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
                'period_from' => '2026-01-01T00:00:00.000Z',
                'period_to' => '2026-02-01T00:00:00.000Z',
            ]);
            self::fail('Expected ApiException');
        } catch (ApiException $e) {
            self::assertSame(422, $e->httpStatus);
            self::assertSame('FROM_TIME_INVALID_SELECTION', $e->errorCode);
            self::assertStringContainsString('"details"', (string) $e->rawBody);
            self::assertIsArray($e->details);
            self::assertArrayHasKey(0, $e->details);
            self::assertSame('t1', $e->details[0]['time_entry_id'] ?? null);
            self::assertSame('ALREADY_BILLED', $e->details[0]['code'] ?? null);
        }
    }
}
