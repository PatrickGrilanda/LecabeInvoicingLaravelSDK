<?php

declare(strict_types=1);

namespace Lecabe\Invoicing\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Lecabe\Invoicing\Config;
use Lecabe\Invoicing\Exception\ApiException;
use Lecabe\Invoicing\InvoicingClient;
use Lecabe\Invoicing\Tests\TestCase;

final class InvoicePdfTest extends TestCase
{
    public function testDownloadReturnsPdfBytesFromBody(): void
    {
        $expected = "%PDF-1.4 minimal fixture\n";
        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/pdf'], $expected),
        ]);
        $http = new Client([
            'handler' => HandlerStack::create($mock),
            'base_uri' => 'http://127.0.0.1:3000/',
            'http_errors' => false,
        ]);
        $api = new InvoicingClient(new Config(apiKey: 'k'), $http);

        $actual = $api->invoicePdf()->download('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee');

        self::assertSame($expected, $actual);
        self::assertStringStartsWith('%PDF', $actual);
    }

    public function test401MapsToApiException(): void
    {
        $mock = new MockHandler([
            new Response(401, [], '{"error":{"code":"UNAUTHORIZED","message":"bad key"}}'),
        ]);
        $http = new Client([
            'handler' => HandlerStack::create($mock),
            'base_uri' => 'http://127.0.0.1:3000/',
            'http_errors' => false,
        ]);
        $api = new InvoicingClient(new Config(apiKey: 'k'), $http);

        try {
            $api->invoicePdf()->download('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee');
            self::fail('Expected ApiException');
        } catch (ApiException $e) {
            self::assertSame(401, $e->httpStatus);
            self::assertSame('UNAUTHORIZED', $e->errorCode);
        }
    }
}
