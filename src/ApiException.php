<?php

declare(strict_types=1);

namespace VedAstro;

/**
 * Raised when the API reports a failure, returns a non-success HTTP status, or sends a response
 * the SDK cannot decode.
 *
 * The API key is never included in {@see getMessage()}: any occurrence of the configured key is
 * replaced with `[redacted]` so a credential cannot leak into logs.
 */
class ApiException extends VedAstroException
{
    private string $endpoint;
    private int $statusCode;

    public function __construct(string $endpoint, int $statusCode, string $message)
    {
        parent::__construct(sprintf('vedastro: %s (HTTP %d): %s', $endpoint, $statusCode, $message));
        $this->endpoint = $endpoint;
        $this->statusCode = $statusCode;
    }

    /** The calculator endpoint that failed, for example `PlanetRasiD1Sign`. */
    public function endpoint(): string
    {
        return $this->endpoint;
    }

    /** The HTTP status code, or `0` when no response was received. */
    public function statusCode(): int
    {
        return $this->statusCode;
    }
}
