<?php

declare(strict_types=1);

namespace VedAstro;

/**
 * A decoded API response: the envelope's status plus the unwrapped payload.
 *
 * Calculators return the payload directly. Ask the client for this type when you want the
 * status text or the raw envelope (which includes the echoed `Input` block):
 *
 * ```php
 * $response = $client->callWithResponse('PlanetRasiD1Sign', ['planetName' => 'Sun', 'time' => $birth]);
 * $response->isSuccess();                       // true
 * Response::string($response->payload(), 'Name'); // "Libra"
 * ```
 */
final class ApiResponse
{
    /**
     * @param string $endpoint The calculator that was called.
     * @param string $status   The API's status text, `"Pass"` on success.
     * @param mixed $payload   The unwrapped payload.
     * @param array<string, mixed> $envelope The full decoded response body.
     */
    public function __construct(
        public readonly string $endpoint,
        public readonly string $status,
        public readonly mixed $payload,
        public readonly array $envelope = [],
    ) {
    }

    /** The API's status text, normally `"Pass"`. */
    public function status(): string
    {
        return $this->status;
    }

    /** The unwrapped payload: array, string, float, int, bool or null. */
    public function payload(): mixed
    {
        return $this->payload;
    }

    /**
     * The full decoded response body, including the echoed `Input` block.
     *
     * For an SVG response this is empty, because the body is markup rather than JSON.
     *
     * @return array<string, mixed>
     */
    public function envelope(): array
    {
        return $this->envelope;
    }

    /** Reports whether the API reported success. */
    public function isSuccess(): bool
    {
        return strcasecmp($this->status, 'Fail') !== 0;
    }

    /** Reports whether the API reported failure. */
    public function isFailure(): bool
    {
        return !$this->isSuccess();
    }
}
