<?php

declare(strict_types=1);

namespace VedAstro;

/**
 * A captured HTTP response, as returned by a {@see Transport}.
 */
final class TransportResponse
{
    public function __construct(
        public readonly int $statusCode,
        public readonly string $body,
        public readonly string $contentType = '',
    ) {
    }
}
