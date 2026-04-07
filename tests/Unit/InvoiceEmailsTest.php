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

final class InvoiceEmailsTest extends TestCase
{
    public function testPostPayloadShapeAndPath(): void
    {
        $container = [];
        $history = Middleware::history($container);
        $ok = json_encode(['ok' => true, 'message_id' => 'msg-1'], JSON_THROW_ON_ERROR);
        $mock = new MockHandler([new Response(200, [], $ok)]);
        $handler = HandlerStack::create($mock);
        $handler->push($history);
        $http = new Client(['handler' => $handler, 'base_uri' => 'http://127.0.0.1:3000/', 'http_errors' => false]);
        $api = new InvoicingClient(new Config(apiKey: 'k'), $http);

        $inv = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
        $api->invoiceEmails()->send($inv, [
            'to' => 'buyer@example.com',
            'subject' => 'Your invoice',
            'attach_pdf' => false,
            'fiscal_attachment' => [
                'filename' => 'nf.xml',
                'content_base64' => 'UEsDBA==',
            ],
        ]);

        $req = $container[0]['request'];
        self::assertSame('POST', $req->getMethod());
        self::assertStringEndsWith('/v1/invoices/' . $inv . '/emails', $req->getUri()->getPath());
        $decoded = json_decode((string) $req->getBody(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('buyer@example.com', $decoded['to']);
        self::assertSame('Your invoice', $decoded['subject']);
        self::assertFalse($decoded['attach_pdf']);
        self::assertSame('nf.xml', $decoded['fiscal_attachment']['filename']);
        self::assertSame('UEsDBA==', $decoded['fiscal_attachment']['content_base64']);
    }

    public function test503EmailNotConfigured(): void
    {
        $body = json_encode([
            'error' => [
                'code' => 'EMAIL_NOT_CONFIGURED',
                'message' => 'SMTP not set',
            ],
        ], JSON_THROW_ON_ERROR);
        $mock = new MockHandler([new Response(503, [], $body)]);
        $http = new Client([
            'handler' => HandlerStack::create($mock),
            'base_uri' => 'http://127.0.0.1:3000/',
            'http_errors' => false,
        ]);
        $api = new InvoicingClient(new Config(apiKey: 'k'), $http);

        try {
            $api->invoiceEmails()->send('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', ['to' => 'a@b.co']);
            self::fail('Expected ApiException');
        } catch (ApiException $e) {
            self::assertSame(503, $e->httpStatus);
            self::assertSame('EMAIL_NOT_CONFIGURED', $e->errorCode);
        }
    }
}
