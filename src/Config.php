<?php

declare(strict_types=1);

namespace Lecabe\Invoicing;

/**
 * Immutable SDK configuration.
 */
final readonly class Config
{
    public function __construct(
        public string $baseUri = 'http://127.0.0.1:3000',
        public string $apiKey = '',
        public float $timeout = 30.0,
    ) {
    }
}
