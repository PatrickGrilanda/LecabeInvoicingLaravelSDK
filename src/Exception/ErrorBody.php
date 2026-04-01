<?php

declare(strict_types=1);

namespace Lecabe\Invoicing\Exception;

/**
 * Parsed API error envelope: { "error": { "code", "message", "details?" } }.
 */
final readonly class ErrorBody
{
    public function __construct(
        public string $code,
        public string $message,
        public mixed $details = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['code'] ?? 'UNKNOWN'),
            (string) ($data['message'] ?? ''),
            $data['details'] ?? null,
        );
    }
}
