<?php

declare(strict_types=1);

namespace Lecabe\Invoicing\Exception;

use Psr\Http\Message\ResponseInterface;

class ApiException extends \Exception
{
    public function __construct(
        string $message,
        public readonly int $httpStatus,
        public readonly string $errorCode,
        public readonly mixed $details = null,
        public readonly ?string $rawBody = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function fromResponse(ResponseInterface $response): self
    {
        $status = $response->getStatusCode();
        $body = (string) $response->getBody();
        $json = json_decode($body, true);

        if (\is_array($json) && isset($json['error']) && \is_array($json['error'])) {
            $eb = ErrorBody::fromArray($json['error']);

            return new self($eb->message, $status, $eb->code, $eb->details, $body);
        }

        $msg = $body !== '' ? $body : 'HTTP ' . $status;

        return new self($msg, $status, 'HTTP_' . $status, null, $body !== '' ? $body : null);
    }
}
