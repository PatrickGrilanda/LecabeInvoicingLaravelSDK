<?php

declare(strict_types=1);

namespace Lecabe\Invoicing\Tests\Unit;

use Lecabe\Invoicing\Exception\ApiException;
use Lecabe\Invoicing\Tests\TestCase;

final class ApiExceptionTest extends TestCase
{
    public function testFromJsonErrorEnvelope(): void
    {
        $json = json_encode([
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => 'Validation failed',
                'details' => ['field' => 'x'],
            ],
        ], JSON_THROW_ON_ERROR);

        $response = new \GuzzleHttp\Psr7\Response(422, ['Content-Type' => 'application/json'], $json);
        $ex = ApiException::fromResponse($response);

        self::assertSame(422, $ex->httpStatus);
        self::assertSame('VALIDATION_ERROR', $ex->errorCode);
        self::assertSame('Validation failed', $ex->getMessage());
        self::assertSame(['field' => 'x'], $ex->details);
        self::assertSame($json, $ex->rawBody);
    }

    public function testFromNonJsonBody(): void
    {
        $response = new \GuzzleHttp\Psr7\Response(500, [], 'Internal problem');
        $ex = ApiException::fromResponse($response);

        self::assertSame(500, $ex->httpStatus);
        self::assertSame('HTTP_500', $ex->errorCode);
        self::assertStringContainsString('Internal problem', $ex->getMessage());
    }
}
